<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\WorkflowDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private WorkflowDocumentService $documents)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with($this->relationsForUser($user));

        // Filter based on user role
        if ($user->isSales()) {
            $query->where('sales_user_id', $user->id);
        } elseif ($user->isFactory()) {
            $query->where(function ($builder) use ($user) {
                $builder->whereIn('status', ['sent_to_factory', 'factory_pricing'])
                        ->orWhere('factory_user_id', $user->id);
            });
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
                'order' => $order->load($this->relationsForUser($request->user()))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    public function show(Order $order)
    {
        $this->authorizeOrderAccess($order);

        $order->load($this->relationsForUser(request()->user()));

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
            if ($request->user()->isSales() && $request->has('customer')) {
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
                'order' => $order->load($this->relationsForUser($request->user()))
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

        if (!$order->isManagerReview() || !$order->factory_cost) {
            return response()->json(['message' => 'Order must have factory pricing before approval'], 400);
        }

        $validator = $request->validate([
            'profit_margin_percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $oldValues = $order->toArray();

            $quantity = max(1, (int) ($order->quantity ?: 1));
            $sellingPrice = (float) $order->factory_cost * (1 + ((float) $request->profit_margin_percentage / 100));
            $totalPrice = round($sellingPrice * $quantity, 2);
            $netProfit = round(($sellingPrice - (float) $order->factory_cost) * $quantity, 2);

            $order->update([
                'profit_margin_percentage' => $request->profit_margin_percentage,
                'selling_price' => $sellingPrice,
                'final_price' => $totalPrice,
                'total_price' => $totalPrice,
                'net_profit' => $netProfit,
                'status' => 'approved',
                'manager_approval' => true,
            ]);

            AuditLog::log('order_approved', $order, $oldValues, $order->toArray());

            return response()->json([
                'message' => 'Order approved successfully',
                'order' => $order->load($this->relationsForUser($request->user()))
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to approve order: ' . $e->getMessage()], 500);
        }
    }

    public function customerApproval(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);

        if ($request->user()->isFactory()) {
            return response()->json(['message' => 'Factory users cannot record customer approval'], 403);
        }

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

    public function confirmPayment(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);

        if ($request->user()->isFactory()) {
            return response()->json(['message' => 'Factory users cannot confirm payment'], 403);
        }

        if (!$order->isCustomerApproved()) {
            return response()->json(['message' => 'Order must be approved by customer first'], 400);
        }

        $order->update([
            'status' => 'completed',
            'payment_confirmed' => true,
        ]);

        AuditLog::log('payment_confirmed', $order);

        return response()->json(['message' => 'Payment confirmed and order completed']);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);

        $validator = $request->validate([
            'status' => ['required', Rule::in(['draft', 'sent_to_factory', 'factory_pricing', 'manager_review', 'pending_approval', 'approved', 'customer_approved', 'payment_confirmed', 'completed'])],
        ]);

        $user = $request->user();

        if ($user->isFactory()) {
            return response()->json(['message' => 'Factory users cannot change order status directly'], 403);
        }

        if ($user->isSales()) {
            if ($order->sales_user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ($order->status !== 'draft' || $request->status !== 'sent_to_factory') {
                return response()->json(['message' => 'Sales users can only submit draft orders to the factory'], 422);
            }
        }

        $oldStatus = $order->status;
        $order->update(['status' => $request->status]);

        AuditLog::log('status_changed', $order, ['status' => $oldStatus], ['status' => $request->status]);

        return response()->json(['message' => 'Status updated successfully']);
    }

    public function downloadQuotationPdf(Order $order)
    {
        $this->authorizeOrderAccess($order);

        try {
            $document = $this->documents->generateQuotation($order);

            return $this->downloadGeneratedDocument($document['path'], $document['filename']);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'تعذر توليد عرض السعر بصيغة PDF.';

            return $this->documentErrorResponse($message, 422);
        } catch (\Throwable $exception) {
            return $this->documentErrorResponse('تعذر توليد عرض السعر بصيغة PDF: ' . $exception->getMessage(), 500);
        }
    }

    public function downloadInvoicePdf(Order $order)
    {
        $this->authorizeOrderAccess($order);

        try {
            $document = $this->documents->generateInvoice($order);

            return $this->downloadGeneratedDocument($document['path'], $document['filename']);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'تعذر توليد الفاتورة بصيغة PDF.';

            return $this->documentErrorResponse($message, 422);
        } catch (\Throwable $exception) {
            return $this->documentErrorResponse('تعذر توليد الفاتورة بصيغة PDF: ' . $exception->getMessage(), 500);
        }
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

        $rules['attachments.*'] = 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240';

        return validator($request->all(), $rules);
    }

    private function relationsForUser($user): array
    {
        $relations = ['salesUser', 'factoryUser', 'attachments.uploadedBy', 'items'];

        if (!$user->isFactory()) {
            $relations[] = 'customer';
        }

        return $relations;
    }

    private function downloadGeneratedDocument(string $path, string $downloadName)
    {
        $disk = config('workflow.documents_disk', 'public');

        if (!Storage::disk($disk)->exists($path)) {
            return $this->documentErrorResponse('الملف المولّد غير موجود.', 404);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }

    private function documentErrorResponse(string $message, int $statusCode)
    {
        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json(['message' => $message], $statusCode);
        }

        return redirect()->back()->with('error', $message);
    }

    private function handleAttachments($request, $order, $type)
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $disk = config('workflow.uploads_disk', 'public');
                $folder = $type === 'sales_upload'
                    ? config('workflow.sales_upload_root', 'sales_uploads')
                    : config('workflow.factory_upload_root', 'factory_uploads');
                $path = $file->store($folder, $disk);

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

        if (
            $user->isFactory()
            && $order->factory_user_id !== $user->id
            && $order->pending_change_requested_by !== $user->id
            && !in_array($order->status, ['sent_to_factory', 'factory_pricing'], true)
        ) {
            abort(403, 'Unauthorized');
        }
    }
}
