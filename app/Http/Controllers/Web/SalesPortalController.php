<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DocumentController;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Services\OrderChangeService;
use App\Services\UserNotificationService;
use App\Services\WorkflowDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesPortalController extends Controller
{
    public function __construct(
        private WorkflowDocumentService $documents,
        private OrderChangeService $orderChanges,
        private UserNotificationService $notifications
    )
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['customer', 'attachments.uploadedBy', 'salesUser', 'factoryUser', 'items']);

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
            $orderPayload = $this->buildOrderPayloadFromItems($validated['items']);
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
                'customer_name' => $customer->full_name,
                'sales_user_id' => $request->user()->id,
                'product_name' => $orderPayload['product_name'],
                'quantity' => $orderPayload['quantity'],
                'specifications' => $orderPayload['specifications'],
                'customer_notes' => $validated['customer_notes'] ?? null,
                'status' => 'draft',
            ]);

            $this->syncOrderItems($order, $orderPayload['items']);
            $this->storeAttachments($request, $order, 'sales_upload');
            AuditLog::log('order_created', $order, null, $order->toArray());
        });

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم إنشاء الطلب بنجاح.');
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);
        $order->load(['customer', 'attachments.uploadedBy', 'salesUser', 'factoryUser', 'items', 'pendingAdjustmentLog.requester']);

        return view('sales.orders.form', [
            'order' => $order,
            'mode' => 'edit',
        ]);
    }

    public function createAdjustment(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);
        $order->load(['customer', 'attachments.uploadedBy', 'salesUser', 'factoryUser', 'items', 'pendingAdjustmentLog.requester']);

        if ($order->isDraft()) {
            return redirect()->route('sales.orders.edit', $order)->with('error', 'يمكن تعديل الطلبات المسودة مباشرة دون إنشاء طلب تعديل مستقل.');
        }

        if ($order->hasPendingChanges()) {
            return redirect()->route('sales.orders.edit', $order)->with('error', 'يوجد طلب تعديل معلّق بالفعل بانتظار اعتماد المدير.');
        }

        if (!$request->user()->isAdmin() && !$order->canRequestAdjustmentBy($request->user())) {
            return redirect()->route('sales.orders.edit', $order)->with('error', 'لا يمكنك إنشاء طلب تعديل على هذا الطلب حالياً.');
        }

        return view('sales.orders.adjustment-request', [
            'order' => $order,
        ]);
    }

    public function storeAdjustment(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        if ($order->isDraft()) {
            return redirect()->route('sales.orders.edit', $order)->with('error', 'يمكن تعديل الطلبات المسودة مباشرة دون إنشاء طلب تعديل مستقل.');
        }

        if ($order->hasPendingChanges()) {
            return redirect()->route('sales.orders.edit', $order)->with('error', 'يوجد طلب تعديل معلّق بالفعل بانتظار اعتماد المدير.');
        }

        $validated = $this->validateSalesOrder($request);
        $orderPayload = $this->buildOrderPayloadFromItems($validated['items']);
        $changePayload = [
            'customer' => [
                'full_name' => $validated['customer_full_name'],
                'address' => $validated['customer_address'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
            ],
            'order' => [
                'customer_name' => $validated['customer_full_name'],
                'product_name' => $orderPayload['product_name'],
                'quantity' => $orderPayload['quantity'],
                'specifications' => $orderPayload['specifications'],
                'customer_notes' => $validated['customer_notes'] ?? null,
            ],
            'items' => $orderPayload['items'],
        ];

        $stagedAttachments = $this->orderChanges->stageAttachments($request->file('attachments', []), 'sales_upload');
        $this->orderChanges->submitSalesChangeRequest($order, $request->user(), $changePayload, $stagedAttachments);

        return redirect()->route('sales.orders.edit', $order)->with('success', 'تم إرسال طلب التعديل لاعتماد المدير.');
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeSalesAccess($request, $order);

        if ($order->hasPendingChanges()) {
            return back()->with('error', 'يوجد تعديل معلّق بالفعل بانتظار اعتماد المدير.');
        }

        if (!$request->user()->isAdmin() && !$order->isDraft()) {
            return redirect()->route('sales.orders.adjustments.create', $order)->with('error', 'تم قفل الحقول الأصلية لهذا الطلب. استخدم نموذج طلب التعديل المنفصل.');
        }

        if (!$order->canBeEditedBy($request->user())) {
            return back()->with('error', 'لا يمكن تعديل الطلب في حالته الحالية.');
        }

        $validated = $this->validateSalesOrder($request);
        $orderPayload = $this->buildOrderPayloadFromItems($validated['items']);
        $changePayload = [
            'customer' => [
                'full_name' => $validated['customer_full_name'],
                'address' => $validated['customer_address'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
            ],
            'order' => [
                'customer_name' => $validated['customer_full_name'],
                'product_name' => $orderPayload['product_name'],
                'quantity' => $orderPayload['quantity'],
                'specifications' => $orderPayload['specifications'],
                'customer_notes' => $validated['customer_notes'] ?? null,
            ],
            'items' => $orderPayload['items'],
        ];

        if ($request->user()->isAdmin()) {
            DB::transaction(function () use ($request, $order, $changePayload) {
                $oldValues = $this->salesAuditPayload($order->fresh(['customer', 'items']));

                $order->customer?->update($changePayload['customer']);
                $order->update($changePayload['order']);
                $this->syncOrderItems($order, $changePayload['items']);
                $this->storeAttachments($request, $order, 'sales_upload');

                AuditLog::log(
                    'order_updated',
                    $order,
                    $oldValues,
                    $this->salesAuditPayload($order->fresh(['customer', 'items']))
                );
            });

            return redirect()->route('sales.orders.edit', $order)->with('success', 'تم تحديث الطلب بنجاح.');
        }

        DB::transaction(function () use ($request, $order, $changePayload) {
            $oldValues = $this->salesAuditPayload($order->fresh(['customer', 'items']));

            $order->customer?->update($changePayload['customer']);
            $order->update($changePayload['order']);
            $this->syncOrderItems($order, $changePayload['items']);
            $this->storeAttachments($request, $order, 'sales_upload');

            AuditLog::log(
                'order_updated',
                $order,
                $oldValues,
                $this->salesAuditPayload($order->fresh(['customer', 'items']))
            );
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
        $order->update(['status' => 'sent_to_factory']);
        AuditLog::log('status_changed', $order, ['status' => $oldStatus], ['status' => 'sent_to_factory']);

        $admins = User::query()->where('role', 'admin')->where('is_active', true)->get();
        $factoryUsers = User::query()->where('role', 'factory')->where('is_active', true)->get();

        $this->notifications->send(
            $admins,
            new OrderStatusUpdatedNotification(
                $order,
                'تم إرسال طلب جديد للمراجعة',
                sprintf('تم نقل الطلب %s من المبيعات إلى المصنع وهو بانتظار التسعير.', $order->order_number),
                route('admin.orders.review', $order),
                ['status' => 'sent_to_factory']
            )
        );

        $this->notifications->send(
            $factoryUsers,
            new OrderStatusUpdatedNotification(
                $order,
                'طلب جديد بانتظار التسعير',
                sprintf('الطلب %s أصبح متاحًا للمصنع لإعداد التسعير.', $order->order_number),
                route('factory.orders.edit', $order),
                ['status' => 'sent_to_factory']
            )
        );

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
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.description' => ['nullable', 'string'],
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

    private function buildOrderPayloadFromItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = [
                'item_name' => trim((string) ($item['item_name'] ?? '')),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'description' => trim((string) ($item['description'] ?? '')),
            ];
        }

        $firstItem = $normalized[0];
        $totalQuantity = array_sum(array_column($normalized, 'quantity'));
        $summaryLines = array_map(function ($item) {
            return trim($item['item_name'] . ($item['description'] !== '' ? ' - ' . $item['description'] : ''));
        }, $normalized);

        return [
            'items' => $normalized,
            'product_name' => count($normalized) === 1 ? $firstItem['item_name'] : $firstItem['item_name'] . ' +' . (count($normalized) - 1) . ' items',
            'quantity' => $totalQuantity,
            'specifications' => implode(PHP_EOL, $summaryLines),
        ];
    }

    private function syncOrderItems(Order $order, array $items): void
    {
        $order->items()->delete();
        $order->items()->createMany($items);
    }

    private function salesAuditPayload(Order $order): array
    {
        return [
            'customer' => [
                'full_name' => $order->customer?->full_name,
                'address' => $order->customer?->address,
                'phone' => $order->customer?->phone,
                'email' => $order->customer?->email,
            ],
            'order' => [
                'customer_name' => $order->customer_name,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'specifications' => $order->specifications,
                'customer_notes' => $order->customer_notes,
                'status' => $order->status,
            ],
            'items' => $order->resolvedItems()->map(fn ($item) => [
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'description' => $item->description,
            ])->values()->all(),
        ];
    }
}
