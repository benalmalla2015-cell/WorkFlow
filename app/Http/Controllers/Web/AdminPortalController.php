<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Services\OrderChangeService;
use App\Services\OrderPricingService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class AdminPortalController extends Controller
{
    public function __construct(
        private OrderChangeService $orderChanges,
        private OrderPricingService $pricing,
        private UserNotificationService $notifications
    )
    {
    }

    public function dashboard(Request $request)
    {
        $ordersQuery = Order::withoutGlobalScopes()->with([
            'customer',
            'salesUser',
            'factoryUser',
            'items',
            'pendingChangeRequester',
            'pendingAdjustmentLog.requester',
        ]);

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->string('status'));
        } else {
            $ordersQuery->whereIn('status', ['manager_review', 'pending_approval']);
        }

        $orders = $ordersQuery->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $allOrders = Order::withoutGlobalScopes()->with(['customer', 'salesUser', 'factoryUser', 'items'])->get();
        $pendingApprovals = Order::withoutGlobalScopes()
            ->with(['customer', 'salesUser', 'factoryUser', 'items'])
            ->where('status', 'manager_review')
            ->whereNull('pending_changes')
            ->whereDoesntHave('pendingAdjustmentLog', fn ($query) => $query->where('status', 'pending'))
            ->orderByDesc('updated_at')
            ->get();
        $pendingChangeRequests = Order::withoutGlobalScopes()
            ->with(['customer', 'salesUser', 'factoryUser', 'pendingChangeRequester', 'pendingAdjustmentLog.requester'])
            ->where(function ($builder) {
                $builder->whereNotNull('pending_changes')
                    ->orWhereHas('pendingAdjustmentLog', fn ($query) => $query->where('status', 'pending'));
            })
            ->orderByDesc('pending_change_requested_at')
            ->get();

        return view('admin.dashboard', [
            'orders' => $orders,
            'pendingApprovals' => $pendingApprovals,
            'pendingChangeRequests' => $pendingChangeRequests,
            'stats' => [
                'total_orders' => $allOrders->count(),
                'pending_orders' => $pendingApprovals->count() + $pendingChangeRequests->count(),
                'pending_manager_reviews' => $pendingApprovals->count(),
                'pending_adjustments' => $pendingChangeRequests->count(),
                'default_profit_margin' => (float) Setting::get('default_profit_margin', 20),
            ],
            'filters' => $request->only(['status']),
        ]);
    }

    public function reviewOrder(Order $order)
    {
        $order = Order::withoutGlobalScopes()
            ->with(['customer', 'salesUser', 'factoryUser', 'attachments.uploadedBy', 'items'])
            ->findOrFail($order->id);

        if ($order->hasPendingChanges()) {
            return redirect()->route('admin.orders.pending-changes.review', $order);
        }

        return view('admin.orders.review', [
            'order' => $order,
            'defaultMargin' => (float) Setting::get('default_profit_margin', 20),
            'pricingSummary' => $this->pricing->summarize($order),
        ]);
    }

    public function reviewPendingChange(Order $order)
    {
        $order = Order::withoutGlobalScopes()
            ->with(['customer', 'salesUser', 'factoryUser', 'attachments.uploadedBy', 'items', 'pendingChangeRequester', 'pendingAdjustmentLog.requester'])
            ->findOrFail($order->id);

        if (!$order->hasPendingChanges()) {
            return redirect()
                ->route('admin.orders.review', $order)
                ->with('error', 'لا يوجد طلب تعديل معلّق لهذا الطلب.');
        }

        $pendingChanges = $order->pendingAdjustmentLog
            ? [
                'current' => $order->pendingAdjustmentLog?->current_payload,
                'proposed' => $order->pendingAdjustmentLog?->proposed_payload,
                'changed_fields' => $order->pendingAdjustmentLog?->changed_fields,
                'requested_by' => [
                    'id' => $order->pendingAdjustmentLog?->requester?->id,
                    'name' => $order->pendingAdjustmentLog?->requester?->name,
                    'role' => $order->pendingAdjustmentLog?->requester?->role,
                ],
                'previous_status' => $order->pendingAdjustmentLog?->previous_status,
                'target_status' => $order->pendingAdjustmentLog?->target_status,
            ]
            : (is_array($order->pending_changes) ? $order->pending_changes : []);

        return view('admin.orders.pending-change-review', [
            'order' => $order,
            'pendingChanges' => $pendingChanges,
        ]);
    }

    public function approvePendingChange(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($order->id);

        if (!$order->hasPendingChanges()) {
            return back()->with('error', 'لا يوجد طلب تعديل معلّق لهذا الطلب.');
        }

        try {
            $this->orderChanges->approvePendingChange($order, $request->user());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'تعذر اعتماد التعديل بسبب بيانات ناقصة أو غير صالحة.');
        }

        return redirect()->route('admin.dashboard')->with('success', 'تم اعتماد التعديلات المقترحة وتثبيت البيانات الأصلية.');
    }

    public function rejectPendingChange(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($order->id);

        if (!$order->hasPendingChanges()) {
            return back()->with('error', 'لا يوجد طلب تعديل معلّق لهذا الطلب.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->orderChanges->rejectPendingChange($order, $request->user(), $validated['reason'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'تعذر رفض التعديل بسبب بيانات ناقصة أو غير صالحة.');
        }

        return redirect()->route('admin.dashboard')->with('success', 'تم رفض التعديلات المقترحة وإعادة الطلب لحالته السابقة.');
    }

    public function requestPendingChangeRevision(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($order->id);

        if (!$order->hasPendingChanges()) {
            return back()->with('error', 'لا يوجد طلب تعديل معلّق لهذا الطلب.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->orderChanges->requestPendingChangeRevision($order, $request->user(), $validated['reason'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'تعذر إعادة التعديل للاستكمال بسبب بيانات ناقصة أو غير صالحة.');
        }

        return redirect()->route('admin.dashboard')->with('success', 'تم طلب تعديل إضافي من الموظف المعني.');
    }

    public function updateWorkflowStage(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($order->id);
        if ($order->hasPendingChanges() || $order->isPendingApproval()) {
            return back()->with('error', 'لا يمكن تغيير المرحلة يدويًا أثناء وجود طلب تعديل معلّق بانتظار الاعتماد.');
        }

        $validated = $request->validate([
            'workflow_stage' => ['required', Rule::in(['new', 'processing', 'ready', 'completed'])],
        ]);

        $oldValues = $order->toArray();
        $stage = $validated['workflow_stage'];
        if (in_array($stage, ['ready', 'completed'], true) && (!$order->hasCompleteFactoryItemPricing() || !$order->production_days)) {
            return back()->with('error', 'لا يمكن نقل الطلب إلى جاهز أو مكتمل قبل اكتمال تسعير جميع العناصر واعتماد مدة الإنتاج.');
        }

        $processingStatus = $order->factory_cost ? 'factory_pricing' : 'sent_to_factory';
        $updates = match ($stage) {
            'new' => [
                'status' => 'draft',
                'manager_approval' => false,
                'customer_approval' => false,
                'payment_confirmed' => false,
            ],
            'processing' => [
                'status' => $processingStatus,
                'manager_approval' => false,
                'customer_approval' => false,
                'payment_confirmed' => false,
            ],
            'ready' => [
                'status' => 'approved',
                'manager_approval' => true,
            ],
            'completed' => [
                'status' => 'completed',
                'manager_approval' => true,
                'customer_approval' => true,
                'payment_confirmed' => true,
            ],
        };

        if (in_array($stage, ['ready', 'completed'], true) && $order->factory_cost && !$order->final_price) {
            $margin = (float) ($order->profit_margin_percentage ?: Setting::get('default_profit_margin', 20));
            $finalPrice = (float) $order->factory_cost * (1 + ($margin / 100));
            $updates['profit_margin_percentage'] = $margin;
            $updates['selling_price'] = $finalPrice;
            $updates['final_price'] = $finalPrice;
        }

        $order->update($updates);
        AuditLog::log('workflow_stage_updated', $order, $oldValues, $order->toArray());

        return redirect()->route('admin.dashboard')->with('success', 'تم تحديث مرحلة الطلب بنجاح.');
    }

    public function approveOrder(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->with(['salesUser', 'factoryUser', 'items'])->findOrFail($order->id);

        if ($order->hasPendingChanges()) {
            return redirect()
                ->route('admin.orders.pending-changes.review', $order)
                ->with('error', 'يوجد طلب تعديل معلّق يجب مراجعته أولاً قبل اعتماد الطلب بالطريقة المعتادة.');
        }

        if ($order->isPendingApproval()) {
            return redirect()->route('admin.dashboard')->with('error', 'الطلب ما زال في مرحلة اعتماد تعديل ولا يمكن اعتماده بالطريقة المعتادة الآن.');
        }

        if (!$order->isManagerReview() || !$order->hasCompleteFactoryItemPricing() || !$order->production_days) {
            return back()->with('error', 'لا يمكن اعتماد الطلب قبل أن يُكمل المصنع تسعير جميع العناصر ومدة الإنتاج.');
        }

        $validated = $request->validate([
            'profit_margin_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $oldValues = $order->toArray();
        $order->profit_margin_percentage = (float) $validated['profit_margin_percentage'];
        $summary = $this->pricing->syncOrderPricing($order, (float) $validated['profit_margin_percentage']);

        $order->update([
            'profit_margin_percentage' => $validated['profit_margin_percentage'],
            'supplier_name' => $summary['supplier_name'],
            'product_code' => $summary['product_code'],
            'factory_cost' => $summary['factory_cost_average'],
            'selling_price' => $summary['sales_unit_price_average'],
            'final_price' => $summary['sales_total'],
            'total_price' => $summary['sales_total'],
            'net_profit' => $summary['net_profit'],
            'status' => 'approved',
            'manager_approval' => true,
        ]);

        AuditLog::log('order_approved', $order, $oldValues, $order->toArray());

        if ($order->salesUser) {
            $this->notifications->send(
                $order->salesUser,
                new OrderStatusUpdatedNotification(
                    $order,
                    'تم اعتماد الطلب',
                    sprintf('اعتمد المدير الطلب %s وأصبح جاهزًا للمتابعة التجارية.', $order->order_number),
                    route('sales.orders.edit', $order),
                    ['status' => 'approved']
                )
            );
        }

        if ($order->factoryUser) {
            $this->notifications->send(
                $order->factoryUser,
                new OrderStatusUpdatedNotification(
                    $order,
                    'تم اعتماد الطلب',
                    sprintf('اعتمد المدير الطلب %s وتم إغلاق التعديلات التشغيلية عليه.', $order->order_number),
                    route('factory.orders.edit', $order),
                    ['status' => 'approved']
                )
            );
        }

        return redirect()->route('admin.dashboard')->with('success', 'تم اعتماد الطلب بنجاح.');
    }

    public function requestOrderAdjustment(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->with(['salesUser', 'factoryUser'])->findOrFail($order->id);

        if (!$order->isManagerReview()) {
            return back()->with('error', 'يمكن طلب تعديل إضافي فقط للطلبات الموجودة في مرحلة مراجعة المدير.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldValues = $order->toArray();
        $order->update([
            'status' => 'factory_pricing',
            'manager_approval' => false,
        ]);

        AuditLog::log('order_adjustment_requested_by_admin', $order, $oldValues, [
            'status' => 'factory_pricing',
            'reason' => $validated['reason'] ?? null,
        ]);

        if ($order->factoryUser) {
            $this->notifications->send(
                $order->factoryUser,
                new OrderStatusUpdatedNotification(
                    $order,
                    'مطلوب تعديل إضافي',
                    sprintf('طلب المدير تعديلًا إضافيًا على الطلب %s قبل الاعتماد.', $order->order_number),
                    route('factory.orders.edit', $order),
                    ['status' => 'factory_pricing', 'reason' => $validated['reason'] ?? null]
                )
            );
        }

        if ($order->salesUser) {
            $this->notifications->send(
                $order->salesUser,
                new OrderStatusUpdatedNotification(
                    $order,
                    'الطلب عاد للمراجعة التشغيلية',
                    sprintf('أعاد المدير الطلب %s لطلب تعديل إضافي قبل الاعتماد.', $order->order_number),
                    route('sales.orders.edit', $order),
                    ['status' => 'factory_pricing', 'reason' => $validated['reason'] ?? null]
                )
            );
        }

        return redirect()->route('admin.dashboard')->with('success', 'تمت إعادة الطلب للمصنع لاستكمال التعديل المطلوب.');
    }

    public function rejectOrder(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->with(['salesUser'])->findOrFail($order->id);

        if (!$order->isManagerReview()) {
            return back()->with('error', 'يمكن رفض الطلبات الموجودة في مرحلة مراجعة المدير فقط.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldValues = $order->toArray();
        $order->update([
            'status' => 'draft',
            'manager_approval' => false,
            'customer_approval' => false,
            'payment_confirmed' => false,
        ]);

        AuditLog::log('order_rejected_by_admin', $order, $oldValues, [
            'status' => 'draft',
            'reason' => $validated['reason'] ?? null,
        ]);

        if ($order->salesUser) {
            $this->notifications->send(
                $order->salesUser,
                new OrderStatusUpdatedNotification(
                    $order,
                    'تم رفض الطلب وإعادته للمبيعات',
                    sprintf('أعاد المدير الطلب %s إلى المبيعات لمراجعته من جديد.', $order->order_number),
                    route('sales.orders.edit', $order),
                    ['status' => 'draft', 'reason' => $validated['reason'] ?? null]
                )
            );
        }

        return redirect()->route('admin.dashboard')->with('success', 'تم رفض الطلب وإعادته إلى مرحلة المسودة.');
    }

    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        if ($request->filled('search')) {
            $query->where(function ($builder) use ($request) {
                $term = $request->string('search');
                $builder->where('name', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        $users = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $editingUser = null;

        if ($request->filled('edit')) {
            $editingUser = User::find($request->integer('edit'));
        }

        return view('admin.users.index', [
            'users' => $users,
            'editingUser' => $editingUser,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    public function storeUser(Request $request)
    {
        $validated = $this->validateUser($request, null);
        $validated['is_active'] = $request->boolean('is_active', true);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::findOrCreate($validated['role'], 'web');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles([$validated['role']]);
        AuditLog::log('user_created', $user);

        return redirect()->route('admin.users.index')->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $this->validateUser($request, $user);
        $validated['is_active'] = $request->boolean('is_active');
        $oldValues = $user->toArray();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? false,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::findOrCreate($validated['role'], 'web');

        $user->update($updateData);
        $user->syncRoles([$validated['role']]);
        AuditLog::log('user_updated', $user, $oldValues, $user->toArray());

        return redirect()->route('admin.users.index')->with('success', 'تم تحديث المستخدم بنجاح.');
    }

    public function deleteUser(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي.');
        }

        $oldValues = $user->toArray();
        $user->delete();
        AuditLog::log('user_deleted', null, $oldValues, null);

        return redirect()->route('admin.users.index')->with('success', 'تم حذف المستخدم بنجاح.');
    }

    public function toggleUserStatus(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'لا يمكنك تعطيل حسابك الحالي.');
        }

        $oldValues = ['is_active' => $user->is_active];
        $user->update(['is_active' => !$user->is_active]);
        AuditLog::log('user_status_toggled', $user, $oldValues, ['is_active' => $user->is_active]);

        return redirect()->route('admin.users.index')->with('success', 'تم تحديث حالة المستخدم.');
    }

    public function settings()
    {
        return view('admin.settings', [
            'settingsData' => Setting::all()->pluck('value', 'key'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'default_profit_margin' => ['required', 'numeric', 'min:0', 'max:500'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['required', 'string'],
            'company_phone' => ['required', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_attn' => ['nullable', 'string', 'max:255'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'beneficiary_bank' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:30'],
            'bank_address' => ['nullable', 'string'],
            'beneficiary_address' => ['nullable', 'string'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, (string) $value);
        }

        AuditLog::log('settings_updated', null, null, $validated);

        return redirect()->route('admin.settings.index')->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->string('action') . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['user_id', 'action', 'date_from', 'date_to']),
        ]);
    }

    private function validateUser(Request $request, ?User $user): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['sales', 'factory', 'admin'])],
            'is_active' => ['nullable', 'boolean'],
        ];

        $rules['password'] = $user
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'];

        return $request->validate($rules);
    }
}
