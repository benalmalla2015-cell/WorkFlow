<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class AdminPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $ordersQuery = Order::withoutGlobalScopes()->with(['customer', 'salesUser', 'factoryUser']);

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->string('status'));
        }

        $orders = $ordersQuery->orderByDesc('created_at')->paginate(20)->withQueryString();
        $allOrders = Order::withoutGlobalScopes()->with(['customer', 'salesUser', 'factoryUser'])->get();
        $paidOrders = Order::withoutGlobalScopes()->where('payment_confirmed', true)->get();
        $pendingApprovals = $allOrders->where('status', 'manager_review')->values();
        $totalRevenue = $paidOrders->sum(fn ($order) => (float) ($order->final_price ?? 0));
        $ordersByStatus = $allOrders->groupBy('status')->map->count();
        $defaultMargin = (float) Setting::get('default_profit_margin', 20);
        $monthlyProfit = [];
        $monthlyLabels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyLabels[] = $month->format('M Y');

            $value = Order::withoutGlobalScopes()
                ->where('payment_confirmed', true)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->get()
                ->sum(fn ($order) => max(0, (float) ($order->final_price ?? 0) - (float) ($order->factory_cost ?? 0)));

            $monthlyProfit[] = round($value, 2);
        }

        $employeePerformance = $allOrders
            ->groupBy('sales_user_id')
            ->map(function ($group) {
                $user = optional($group->first())->salesUser;

                return [
                    'name' => $user?->name ?? 'Unknown',
                    'orders_count' => $group->count(),
                    'revenue' => round($group->sum(fn ($order) => (float) ($order->final_price ?? 0)), 2),
                ];
            })
            ->values();

        return view('admin.dashboard', [
            'orders' => $orders,
            'pendingApprovals' => $pendingApprovals,
            'stats' => [
                'total_orders' => $allOrders->count(),
                'pending_orders' => $allOrders->whereIn('status', ['draft', 'factory_pricing', 'manager_review'])->count(),
                'completed_orders' => $allOrders->where('status', 'completed')->count(),
                'total_revenue' => $totalRevenue,
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'orders_by_status' => $ordersByStatus,
                'default_profit_margin' => $defaultMargin,
            ],
            'profitAnalysis' => [
                'monthly_labels' => $monthlyLabels,
                'monthly_profit' => $monthlyProfit,
                'employee_performance' => $employeePerformance,
            ],
            'filters' => $request->only(['status']),
        ]);
    }

    public function reviewOrder(Order $order)
    {
        $order = Order::withoutGlobalScopes()
            ->with(['customer', 'salesUser', 'factoryUser', 'attachments.uploadedBy'])
            ->findOrFail($order->id);

        return view('admin.orders.review', [
            'order' => $order,
            'defaultMargin' => (float) Setting::get('default_profit_margin', 20),
        ]);
    }

    public function approveOrder(Request $request, Order $order)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($order->id);

        if (!$order->isManagerReview() || !$order->factory_cost) {
            return back()->with('error', 'يجب أن يكون الطلب في مرحلة مراجعة المدير وأن يحتوي على تكلفة مصنع.');
        }

        $validated = $request->validate([
            'profit_margin_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $oldValues = $order->toArray();
        $finalPrice = (float) $order->factory_cost * (1 + ((float) $validated['profit_margin_percentage'] / 100));

        $order->update([
            'profit_margin_percentage' => $validated['profit_margin_percentage'],
            'selling_price' => $finalPrice,
            'final_price' => $finalPrice,
            'status' => 'approved',
            'manager_approval' => true,
        ]);

        AuditLog::log('order_approved', $order, $oldValues, $order->toArray());

        return redirect()->route('admin.dashboard')->with('success', 'تم اعتماد الطلب بنجاح.');
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
