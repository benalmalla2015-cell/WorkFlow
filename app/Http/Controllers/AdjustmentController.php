<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\AdjustmentLog;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Setting;
use App\Http\Controllers\NotificationController;
use App\Services\OrderPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdjustmentController extends Controller
{
    public function __construct(private OrderPricingService $pricing)
    {
    }

    /**
     * Get all adjustment requests for admin
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = AdjustmentLog::with(['order', 'requester', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->order_id) {
            $query->where('order_id', $request->order_id);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Get adjustment requests for the authenticated user
     */
    public function myRequests(Request $request)
    {
        $user = $request->user();

        $query = AdjustmentLog::with(['order', 'reviewer'])
            ->where('requester_id', $user->id)
            ->orderBy('created_at', 'desc');

        return response()->json($query->paginate(20));
    }

    /**
     * Request an adjustment for an order
     */
    public function store(Request $request, Order $order)
    {
        $user = $request->user();

        // Check if order can be adjusted
        if (!$order->canRequestAdjustmentBy($user)) {
            return response()->json([
                'message' => 'Cannot request adjustment for this order in current status'
            ], 403);
        }

        // Check if there's already a pending adjustment
        if ($order->hasPendingChanges()) {
            return response()->json([
                'message' => 'There is already a pending adjustment request for this order'
            ], 422);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:price_change,quantity_change,specs_change,cancel_order',
            'reason' => 'required|string|min:10',
            'proposed_payload' => 'required|array',
            'proposed_payload.fields' => 'nullable|array',
            'proposed_payload.items' => 'nullable|array',
            'proposed_payload.items.*.id' => 'required_with:proposed_payload.items|integer',
            'proposed_payload.items.*.supplier_name' => 'required_with:proposed_payload.items|string|max:255',
            'proposed_payload.items.*.product_code' => 'required_with:proposed_payload.items|string|max:100',
            'proposed_payload.items.*.unit_cost' => 'required_with:proposed_payload.items|numeric|min:0.01',
        ]);

        if (empty($validated['proposed_payload']['fields']) && empty($validated['proposed_payload']['items'])) {
            return response()->json([
                'message' => 'Adjustment payload must contain fields or priced items'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create adjustment log
            $adjustment = AdjustmentLog::create([
                'order_id' => $order->id,
                'requester_id' => $user->id,
                'requester_role' => $user->role,
                'type' => $validated['type'],
                'status' => 'pending',
                'previous_status' => $order->status,
                'current_payload' => [
                    'status' => $order->status,
                    'factory_cost' => $order->factory_cost,
                    'selling_price' => $order->selling_price,
                    'final_price' => $order->final_price,
                    'quantity' => $order->quantity,
                    'specifications' => $order->specifications,
                    'supplier_name' => $order->supplier_name,
                    'production_days' => $order->production_days,
                    'items' => $order->resolvedItems()->map(fn ($item) => [
                        'id' => $item->id,
                        'item_name' => $item->item_name,
                        'quantity' => $item->quantity,
                        'description' => $item->description,
                        'supplier_name' => $item->supplier_name,
                        'product_code' => $item->product_code,
                        'unit_cost' => $item->unit_cost,
                    ])->values()->all(),
                    'reason' => $validated['reason'],
                ],
                'proposed_payload' => $validated['proposed_payload'],
            ]);

            // Update order status to pending_approval
            $order->update([
                'pending_changes' => [
                    'adjustment_id' => $adjustment->id,
                    'requested_by' => $user->id,
                    'requested_at' => now()->toIso8601String(),
                    'type' => $validated['type'],
                    'reason' => $validated['reason'],
                ],
                'pending_change_requested_by' => $user->id,
                'pending_change_requested_at' => now(),
                'pending_change_original_status' => $order->status,
            ]);

            // Send notification to admins
            NotificationController::notifyAdjustmentRequested($adjustment);

            DB::commit();

            return response()->json([
                'message' => 'Adjustment request submitted successfully',
                'adjustment' => $adjustment->load(['order', 'requester'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to submit adjustment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve or reject an adjustment
     */
    public function review(Request $request, AdjustmentLog $adjustment)
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$adjustment->isPending()) {
            return response()->json([
                'message' => 'This adjustment request has already been reviewed'
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'review_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $order = $adjustment->order;
            $originalStatus = $adjustment->previous_status;

            // Update adjustment log
            $adjustment->update([
                'reviewer_id' => $user->id,
                'status' => $validated['status'],
                'review_notes' => $validated['review_notes'] ?? null,
                'reviewed_at' => now(),
            ]);

            if ($validated['status'] === 'approved') {
                // Apply the proposed changes to the order
                $proposedPayload = $adjustment->proposed_payload;
                $updateData = [];

                // Map proposed fields to order fields
                if (isset($proposedPayload['fields'])) {
                    foreach ($proposedPayload['fields'] as $field => $value) {
                        $updateData[$field] = $value;
                    }
                }

                if (!empty($proposedPayload['items'])) {
                    $items = $order->items()->get()->keyBy('id');
                    foreach ($proposedPayload['items'] as $itemData) {
                        $item = $items->get((int) ($itemData['id'] ?? 0));

                        if (!$item) {
                            continue;
                        }

                        $item->update([
                            'supplier_name' => $itemData['supplier_name'] ?? null,
                            'product_code' => $itemData['product_code'] ?? null,
                            'unit_cost' => $itemData['unit_cost'] ?? null,
                        ]);
                    }

                    $updateData['manager_approval'] = false;
                }

                // Restore original status if specified, otherwise keep pending_approval
                if (isset($proposedPayload['restore_status']) && $proposedPayload['restore_status']) {
                    $updateData['status'] = $originalStatus;
                }

                // Clear pending changes
                $updateData['pending_changes'] = null;
                $updateData['pending_change_requested_by'] = null;
                $updateData['pending_change_requested_at'] = null;
                $updateData['pending_change_original_status'] = null;

                $order->update($updateData);

                $freshOrder = Order::withoutGlobalScopes()->with('items')->findOrFail($order->id);

                if (!empty($proposedPayload['items'])) {
                    $oldFinancials = [
                        'selling_price' => $freshOrder->selling_price,
                        'final_price' => $freshOrder->final_price,
                        'total_price' => $freshOrder->total_price,
                        'net_profit' => $freshOrder->net_profit,
                    ];

                    $freshOrder->profit_margin_percentage = (float) ($freshOrder->profit_margin_percentage ?: Setting::get('default_profit_margin', 20));
                    $summary = $this->pricing->syncOrderPricing($freshOrder, (float) $freshOrder->profit_margin_percentage);
                    $freshOrder->supplier_name = $summary['supplier_name'];
                    $freshOrder->product_code = $summary['product_code'];
                    $freshOrder->factory_cost = $summary['factory_cost_average'] ?: null;
                    $freshOrder->selling_price = $summary['sales_unit_price_average'] ?: null;
                    $freshOrder->final_price = $summary['sales_total'] ?: null;
                    $freshOrder->total_price = $summary['sales_total'] ?: null;
                    $freshOrder->net_profit = $summary['net_profit'] ?: null;
                    $freshOrder->quotation_path = null;
                    $freshOrder->invoice_path = null;
                    $freshOrder->save();

                    AuditLog::log(
                        'adjustment_approved',
                        $freshOrder,
                        $oldFinancials,
                        [
                            'selling_price' => $freshOrder->selling_price,
                            'final_price' => $freshOrder->final_price,
                            'total_price' => $freshOrder->total_price,
                            'net_profit' => $freshOrder->net_profit,
                        ],
                        ['selling_price', 'final_price', 'total_price', 'net_profit']
                    );

                    $order = $freshOrder;
                } else {
                $quantity = max(1, (int) ($freshOrder->quantity ?: 1));
                $factoryCost = (float) ($freshOrder->factory_cost ?: 0);
                $sellingPrice = (float) ($freshOrder->selling_price ?: 0);

                if ($factoryCost > 0 && $sellingPrice <= 0) {
                    $margin = (float) ($freshOrder->profit_margin_percentage ?: Setting::get('default_profit_margin', 20));
                    $sellingPrice = round($factoryCost * (1 + ($margin / 100)), 2);
                    $freshOrder->profit_margin_percentage = $margin;
                }

                $totalPrice = round($sellingPrice * $quantity, 2);
                $netProfit = round(($sellingPrice - $factoryCost) * $quantity, 2);
                $oldFinancials = [
                    'selling_price' => $freshOrder->selling_price,
                    'final_price' => $freshOrder->final_price,
                    'total_price' => $freshOrder->total_price,
                    'net_profit' => $freshOrder->net_profit,
                ];

                $freshOrder->selling_price = $sellingPrice;
                $freshOrder->final_price = $totalPrice;
                $freshOrder->total_price = $totalPrice;
                $freshOrder->net_profit = $netProfit;
                $freshOrder->save();

                AuditLog::log(
                    'adjustment_approved',
                    $freshOrder,
                    $oldFinancials,
                    [
                        'selling_price' => $sellingPrice,
                        'final_price' => $totalPrice,
                        'total_price' => $totalPrice,
                        'net_profit' => $netProfit,
                    ],
                    ['selling_price', 'final_price', 'total_price', 'net_profit']
                );
                }

            } else {
                // Rejected - just clear the pending changes, don't modify order
                $order->update([
                    'pending_changes' => null,
                    'pending_change_requested_by' => null,
                    'pending_change_requested_at' => null,
                    'pending_change_original_status' => null,
                ]);
            }

            // Send notification to requester
            NotificationController::notifyAdjustmentResolved($adjustment);

            // Notify status change
            NotificationController::notifyOrderStatusChanged(
                $order,
                'pending_approval',
                $order->status
            );

            DB::commit();

            return response()->json([
                'message' => 'Adjustment ' . $validated['status'] . ' successfully',
                'adjustment' => $adjustment->load(['order', 'requester', 'reviewer'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to review adjustment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single adjustment details
     */
    public function show(AdjustmentLog $adjustment)
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isAdmin() &&
            $adjustment->requester_id !== $user->id &&
            $adjustment->order->sales_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($adjustment->load(['order', 'requester', 'reviewer']));
    }

    /**
     * Cancel own pending adjustment request
     */
    public function cancel(Request $request, AdjustmentLog $adjustment)
    {
        $user = $request->user();

        if ($adjustment->requester_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$adjustment->isPending()) {
            return response()->json([
                'message' => 'Cannot cancel a reviewed adjustment request'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = $adjustment->order;

            // Restore original status
            $order->update([
                'status' => $adjustment->previous_status,
                'pending_changes' => null,
                'pending_change_requested_by' => null,
                'pending_change_requested_at' => null,
                'pending_change_original_status' => null,
            ]);

            // Mark adjustment as cancelled
            $adjustment->update([
                'status' => 'cancelled',
                'reviewed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Adjustment request cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to cancel adjustment: ' . $e->getMessage()
            ], 500);
        }
    }
}
