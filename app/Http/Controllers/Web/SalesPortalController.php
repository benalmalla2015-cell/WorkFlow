<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DocumentController;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Services\WorkflowDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesPortalController extends Controller
{
    public function __construct(private WorkflowDocumentService $documents)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['customer', 'attachments.uploadedBy', 'salesUser', 'factoryUser']);

        if ($user->isSales()) {
            $query->where('sales_user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        $orders = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('sales.orders.index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function create()
    {
        return view('sales.orders.form', [
            'order' => new Order(['status' => 'draft']),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSalesOrder($request);
        $order = null;

        DB::transaction(function () use ($request, $validated, &$order) {
            $customer = Customer::updateOrCreate(
                ['phone' => $validated['customer_phone']],
                [
                    'full_name' => $validated['customer_full_name'],
                    'address' => $validated['customer_address'],
                    'email' => $validated['customer_email'] ?? null,
                    'created_by' => $request->user()->id,
                ]
            );

            $lastId = (int) (Order::withTrashed()->select('id')->orderByDesc('id')->lockForUpdate()->value('id') ?? 0);
            $nextId = $lastId + 1;

            $order = Order::create([
                'order_number' => 'ORD-' . now()->format('Y') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'sales_user_id' => $request->user()->id,
                'product_name' => $validated['product_name'],
                'quantity' => $validated['quantity'],
                'specifications' => $validated['specifications'] ?? null,
                'customer_notes' => $validated['customer_notes'] ?? null,
                'status' => 'draft',
            ]);

            $this->storeAttachments($request, $order, 'sales_upload');
            AuditLog::log('order_created', $order, null, $order->toArray());
        });

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم إنشاء الطلب بنجاح.');
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);
        $order->load(['customer', 'attachments.uploadedBy', 'salesUser', 'factoryUser']);

        return view('sales.orders.form', [
            'order' => $order,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        if (!$order->canBeEditedBy($request->user())) {
            return back()->with('error', 'لا يمكن تعديل الطلب في حالته الحالية.');
        }

        $validated = $this->validateSalesOrder($request);

        DB::transaction(function () use ($request, $validated, $order) {
            $oldValues = $order->toArray();

            $order->customer->update([
                'full_name' => $validated['customer_full_name'],
                'address' => $validated['customer_address'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
            ]);

            $order->update([
                'product_name' => $validated['product_name'],
                'quantity' => $validated['quantity'],
                'specifications' => $validated['specifications'] ?? null,
                'customer_notes' => $validated['customer_notes'] ?? null,
            ]);

            $this->storeAttachments($request, $order, 'sales_upload');
            AuditLog::log('order_updated', $order, $oldValues, $order->toArray());
        });

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم تحديث الطلب بنجاح.');
    }

    public function submitToFactory(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        if ($order->status !== 'draft') {
            return back()->with('error', 'يمكن فقط إرسال الطلبات المسودة إلى المصنع.');
        }

        $oldStatus = $order->status;
        $order->update(['status' => 'factory_pricing']);
        AuditLog::log('status_changed', $order, ['status' => $oldStatus], ['status' => 'factory_pricing']);

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم إرسال الطلب للتسعير من المصنع.');
    }

    public function customerApproval(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        if (!$order->isApproved()) {
            return back()->with('error', 'يجب اعتماد الطلب أولاً قبل تسجيل موافقة العميل.');
        }

        $order->update([
            'status' => 'customer_approved',
            'customer_approval' => true,
        ]);

        AuditLog::log('customer_approval', $order);

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم تسجيل موافقة العميل.');
    }

    public function confirmPayment(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        if (!$order->isCustomerApproved()) {
            return back()->with('error', 'يجب تسجيل موافقة العميل قبل تأكيد الدفع.');
        }

        $order->update([
            'status' => 'completed',
            'payment_confirmed' => true,
        ]);

        AuditLog::log('payment_confirmed', $order);

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم تأكيد الدفع وإكمال الطلب.');
    }

    public function generateQuotation(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        try {
            $this->documents->generateQuotation($order);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم توليد عرض السعر بنجاح.');
    }

    public function generateInvoice(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        try {
            $this->documents->generateInvoice($order);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم توليد الفاتورة بنجاح.');
    }

    public function downloadQuotation(Request $request, Order $order, DocumentController $documents)
    {
        $this->authorizeSalesAccess($request, $order);

        return $documents->downloadQuotation($order);
    }

    public function downloadInvoice(Request $request, Order $order, DocumentController $documents)
    {
        $this->authorizeSalesAccess($request, $order);

        return $documents->downloadInvoice($order);
    }

    private function validateSalesOrder(Request $request): array
    {
        return $request->validate([
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_address' => ['required', 'string'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'specifications' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);
    }

    private function authorizeSalesAccess(Request $request, Order $order): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_if($order->sales_user_id !== $request->user()->id, 403);
    }

    private function storeAttachments(Request $request, Order $order, string $type): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

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
