<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['customer', 'salesUser', 'factoryUser', 'attachments']);

        // Filter based on user role
        if ($user->isSales()) {
            $query->where('sales_user_id', $user->id);
        } elseif ($user->isFactory()) {
            $query->where('status', 'factory_pricing')
                  ->orWhere('factory_user_id', $user->id);
        }

        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validator = $this->validateOrderRequest($request, 'create');

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create or find customer
            $customer = Customer::firstOrCreate(
                ['phone' => $request->customer['phone']],
                [
                    'full_name' => $request->customer['full_name'],
                    'address' => $request->customer['address'],
                    'email' => $request->customer['email'] ?? null,
                    'notes' => $request->customer['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]
            );

            // Generate order number
            $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(Order::count() + 1, 5, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'sales_user_id' => $request->user()->id,
                'customer_notes' => $request->customer_notes,
                'product_name' => $request->product_name,
                'quantity' => $request->quantity,
                'specifications' => $request->specifications,
                'status' => 'draft',
            ]);

            // Handle attachments
            if ($request->has('attachments')) {
                $this->handleAttachments($request, $order, 'sales_upload');
            }

            AuditLog::log('order_created', $order, null, $order->toArray());

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load(['customer', 'attachments'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    public function show(Order $order)
    {
        $this->authorizeOrderAccess($order);

        $order->load(['customer', 'salesUser', 'factoryUser', 'attachments.uploadedBy']);

        return response()->json($order);
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);
        
        if (!$order->canBeEditedBy($request->user())) {
            return response()->json(['message' => 'Order cannot be edited in current status'], 403);
        }

        $validator = $this->validateOrderRequest($request, 'update', $order);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $oldValues = $order->toArray();

            // Update customer if provided
            if ($request->has('customer')) {
                $order->customer->update($request->customer);
            }

            // Update order fields based on user role
            $updateData = [];

            if ($request->user()->isSales()) {
                $updateData = [
                    'customer_notes' => $request->customer_notes,
                    'product_name' => $request->product_name,
                    'quantity' => $request->quantity,
                    'specifications' => $request->specifications,
                ];
            } elseif ($request->user()->isFactory()) {
                $updateData = [
                    'supplier_name' => $request->supplier_name,
                    'product_code' => $request->product_code,
                    'factory_cost' => $request->factory_cost,
                    'production_days' => $request->production_days,
                    'factory_user_id' => $request->user()->id,
                    'status' => 'manager_review',
                ];

                // Calculate selling price with default profit margin
                $defaultMargin = Setting::get('default_profit_margin', 20);
                $sellingPrice = $request->factory_cost * (1 + $defaultMargin / 100);
                $updateData['selling_price'] = $sellingPrice;
                $updateData['profit_margin_percentage'] = $defaultMargin;
            }

            $order->update($updateData);

            // Handle new attachments
            if ($request->has('attachments')) {
                $type = $request->user()->isSales() ? 'sales_upload' : 'factory_upload';
                $this->handleAttachments($request, $order, $type);
            }

            AuditLog::log('order_updated', $order, $oldValues, $order->toArray());

            DB::commit();

            return response()->json([
                'message' => 'Order updated successfully',
                'order' => $order->load(['customer', 'attachments'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update order: ' . $e->getMessage()], 500);
        }
    }

    public function approveOrder(Request $request, Order $order)
    {
        if (!$request->user()->canApproveOrders()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = $request->validate([
            'profit_margin_percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $oldValues = $order->toArray();

            // Calculate final price with custom margin
            $finalPrice = $order->factory_cost * (1 + $request->profit_margin_percentage / 100);

            $order->update([
                'profit_margin_percentage' => $request->profit_margin_percentage,
                'selling_price' => $finalPrice,
                'final_price' => $finalPrice,
                'status' => 'approved',
                'manager_approval' => true,
            ]);

            AuditLog::log('order_approved', $order, $oldValues, $order->toArray());

            return response()->json([
                'message' => 'Order approved successfully',
                'order' => $order->load(['customer', 'attachments'])
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to approve order: ' . $e->getMessage()], 500);
        }
    }

    public function customerApproval(Order $order)
    {
        if (!$order->isApproved()) {
            return response()->json(['message' => 'Order must be approved first'], 400);
        }

        $order->update([
            'status' => 'customer_approved',
            'customer_approval' => true,
        ]);

        AuditLog::log('customer_approval', $order);

        return response()->json(['message' => 'Customer approval recorded']);
    }

    public function confirmPayment(Order $order)
    {
        if (!$order->isCustomerApproved()) {
            return response()->json(['message' => 'Order must be approved by customer first'], 400);
        }

        $order->update([
            'status' => 'payment_confirmed',
            'payment_confirmed' => true,
        ]);

        AuditLog::log('payment_confirmed', $order);

        return response()->json(['message' => 'Payment confirmed']);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);

        $validator = $request->validate([
            'status' => ['required', Rule::in(['draft', 'factory_pricing', 'manager_review', 'approved', 'customer_approved', 'payment_confirmed', 'completed'])],
        ]);

        $oldStatus = $order->status;
        $order->update(['status' => $request->status]);

        AuditLog::log('status_changed', $order, ['status' => $oldStatus], ['status' => $request->status]);

        return response()->json(['message' => 'Status updated successfully']);
    }

    private function validateOrderRequest($request, $type, $order = null)
    {
        $rules = [];

        if ($type === 'create') {
            $rules['customer.full_name'] = 'required|string|max:255';
            $rules['customer.address'] = 'required|string';
            $rules['customer.phone'] = 'required|string|max:20';
            $rules['customer.email'] = 'nullable|email|max:255';
            $rules['customer.notes'] = 'nullable|string';
        }

        if ($request->user()->isSales()) {
            $rules['product_name'] = 'required|string|max:255';
            $rules['quantity'] = 'required|integer|min:1';
            $rules['specifications'] = 'nullable|string';
            $rules['customer_notes'] = 'nullable|string';
        }

        if ($request->user()->isFactory()) {
            $rules['supplier_name'] = 'required|string|max:255';
            $rules['product_code'] = 'required|string|max:100';
            $rules['factory_cost'] = 'required|numeric|min:0';
            $rules['production_days'] = 'required|integer|min:1';
        }

        $rules['attachments.*'] = 'nullable|file|max:10240'; // 10MB max

        return validator($request->all(), $rules);
    }

    private function handleAttachments($request, $order, $type)
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $folder = $type === 'sales_upload' ? 'sales_uploads' : 'factory_uploads';
                $path = $file->store($folder, 'public');

                Attachment::create([
                    'order_id' => $order->id,
                    'uploaded_by' => $request->user()->id,
                    'file_name' => $file->hashName(),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'path' => $path,
                    'type' => $type,
                ]);
            }
        }
    }

    private function authorizeOrderAccess($order)
    {
        $user = request()->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isSales() && $order->sales_user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        if ($user->isFactory() && $order->factory_user_id !== $user->id && $order->status !== 'factory_pricing') {
            abort(403, 'Unauthorized');
        }
    }
}
