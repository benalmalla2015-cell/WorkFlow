<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Services\OrderChangeService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactoryPortalController extends Controller
{
    public function __construct(
        private OrderChangeService $orderChanges,
        private UserNotificationService $notifications
    )
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['attachments.uploadedBy', 'salesUser', 'factoryUser', 'items']);

        if (!$user->isAdmin()) {
            $query->where(function ($builder) use ($user) {
                $builder->whereIn('status', ['sent_to_factory', 'factory_pricing'])
                    ->orWhere('factory_user_id', $user->id)
                    ->orWhere('pending_change_requested_by', $user->id);
            });
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

        return view('factory.orders.index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorizeFactoryAccess($request, $order);
        $order->load(['attachments.uploadedBy', 'salesUser', 'factoryUser', 'pendingChangeRequester', 'pendingAdjustmentLog.requester']);

        return view('factory.orders.form', [
            'order' => $order,
            'defaultMargin' => (float) Setting::get('default_profit_margin', 20),
        ]);
    }

    public function createAdjustment(Request $request, Order $order)
    {
        $this->authorizeFactoryAccess($request, $order);
        $order->load(['attachments.uploadedBy', 'salesUser', 'factoryUser', 'pendingAdjustmentLog.requester']);

        if ($order->isDraft()) {
            return redirect()->route('factory.orders.edit', $order)->with('error', 'يمكن تعديل الطلبات المسودة مباشرة دون إنشاء طلب تعديل منفصل.');
        }

        if ($order->hasPendingChanges()) {
            return redirect()->route('factory.orders.edit', $order)->with('error', 'يوجد طلب تعديل معلّق بالفعل بانتظار اعتماد المدير.');
        }

        if (!$request->user()->isAdmin() && !$order->canRequestAdjustmentBy($request->user())) {
            return redirect()->route('factory.orders.edit', $order)->with('error', 'لا يمكنك إنشاء طلب تعديل على هذا الطلب حالياً.');
        }

        return redirect()
            ->route('factory.orders.edit', $order)
            ->with('open_adjustment_modal', true);
    }

    public function storeAdjustment(Request $request, Order $order)
    {
        $this->authorizeFactoryAccess($request, $order);

        if ($order->isDraft()) {
            return redirect()->route('factory.orders.edit', $order)->with('error', 'يمكن تعديل الطلبات المسودة مباشرة دون إنشاء طلب تعديل منفصل.');
        }

        if ($order->hasPendingChanges()) {
            return redirect()->route('factory.orders.edit', $order)->with('error', 'يوجد طلب تعديل معلّق بالفعل بانتظار اعتماد المدير.');
        }

        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'product_code' => ['required', 'string', 'max:100'],
            'factory_cost' => ['required', 'numeric', 'min:0'],
            'production_days' => ['required', 'integer', 'min:1'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        $stagedAttachments = $this->orderChanges->stageAttachments($request->file('attachments', []), 'factory_upload');
        $this->orderChanges->submitFactoryChangeRequest($order, $request->user(), $validated, $stagedAttachments);

        return redirect()->route('factory.orders.edit', $order)->with('success', 'تم إرسال طلب التعديل لاعتماد المدير.');
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeFactoryAccess($request, $order);

        if ($order->hasPendingChanges()) {
            return back()->with('error', 'يوجد تعديل معلّق بالفعل بانتظار اعتماد المدير.');
        }

        if (!$request->user()->isAdmin() && $order->status !== 'sent_to_factory') {
            return redirect()
                ->route('factory.orders.edit', $order)
                ->with('error', 'تم قفل الحقول الأصلية لهذا الطلب. استخدم نموذج طلب التعديل المضمن داخل الصفحة.')
                ->with('open_adjustment_modal', true);
        }

        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'product_code' => ['required', 'string', 'max:100'],
            'factory_cost' => ['required', 'numeric', 'min:0'],
            'production_days' => ['required', 'integer', 'min:1'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($request->user()->isAdmin()) {
            DB::transaction(function () use ($request, $order, $validated) {
                $oldValues = $this->factoryAuditPayload($order->fresh());
                $defaultMargin = (float) Setting::get('default_profit_margin', 20);
                $sellingPrice = (float) $validated['factory_cost'] * (1 + ($defaultMargin / 100));

                $order->update([
                    'supplier_name' => $validated['supplier_name'],
                    'product_code' => $validated['product_code'],
                    'factory_cost' => $validated['factory_cost'],
                    'production_days' => $validated['production_days'],
                    'factory_user_id' => $request->user()->id,
                    'selling_price' => $sellingPrice,
                    'profit_margin_percentage' => $defaultMargin,
                    'status' => 'manager_review',
                ]);

                $this->storeAttachments($request, $order);
                AuditLog::log('order_updated', $order, $oldValues, $this->factoryAuditPayload($order->fresh()));
            });

            return redirect()->route('factory.orders.index')->with('success', 'تم تحديث بيانات المصنع بنجاح.');
        }

        DB::transaction(function () use ($request, $order, $validated) {
            $oldValues = $this->factoryAuditPayload($order->fresh());
            $defaultMargin = (float) Setting::get('default_profit_margin', 20);
            $sellingPrice = (float) $validated['factory_cost'] * (1 + ($defaultMargin / 100));

            $order->update([
                'supplier_name' => $validated['supplier_name'],
                'product_code' => $validated['product_code'],
                'factory_cost' => $validated['factory_cost'],
                'production_days' => $validated['production_days'],
                'factory_user_id' => $request->user()->id,
                'selling_price' => $sellingPrice,
                'profit_margin_percentage' => $defaultMargin,
                'status' => 'manager_review',
            ]);

            $this->storeAttachments($request, $order);
            AuditLog::log('order_updated', $order, $oldValues, $this->factoryAuditPayload($order->fresh()));
        });

        $admins = User::query()->where('role', 'admin')->where('is_active', true)->get();
        $this->notifications->send(
            $admins,
            new OrderStatusUpdatedNotification(
                $order,
                'تم رفع تسعير المصنع',
                sprintf('أكمل المصنع تسعير الطلب %s وأصبح بانتظار مراجعة المدير.', $order->order_number),
                route('admin.orders.review', $order),
                ['status' => 'manager_review']
            )
        );

        if ($order->salesUser) {
            $this->notifications->send(
                $order->salesUser,
                new OrderStatusUpdatedNotification(
                    $order,
                    'تم استلام تسعير المصنع',
                    sprintf('أكمل المصنع تسعير الطلب %s وأُرسل إلى المدير للاعتماد.', $order->order_number),
                    route('sales.orders.edit', $order),
                    ['status' => 'manager_review']
                )
            );
        }

        return redirect()->route('factory.orders.index')->with('success', 'تم إرسال التسعير الأولي إلى الإدارة بنجاح.');
    }

    private function authorizeFactoryAccess(Request $request, Order $order): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_if(
            $order->factory_user_id !== $request->user()->id
            && $order->pending_change_requested_by !== $request->user()->id
            && !in_array($order->status, ['sent_to_factory', 'factory_pricing'], true),
            403
        );
    }

    private function storeAttachments(Request $request, Order $order): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $disk = config('workflow.uploads_disk', 'public');
            $folder = config('workflow.factory_upload_root', 'factory_uploads');
            $path = $file->store($folder, $disk);

            Attachment::create([
                'order_id' => $order->id,
                'uploaded_by' => $request->user()->id,
                'file_name' => $file->hashName(),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'path' => $path,
                'type' => 'factory_upload',
            ]);
        }
    }

    private function factoryAuditPayload(Order $order): array
    {
        return [
            'order' => [
                'supplier_name' => $order->supplier_name,
                'product_code' => $order->product_code,
                'factory_cost' => $order->factory_cost,
                'production_days' => $order->production_days,
                'selling_price' => $order->selling_price,
                'profit_margin_percentage' => $order->profit_margin_percentage,
                'status' => $order->status,
                'factory_user_id' => $order->factory_user_id,
            ],
        ];
    }
}
