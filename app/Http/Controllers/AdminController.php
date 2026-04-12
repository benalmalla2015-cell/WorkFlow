<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use App\Models\Order;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    // User Management
    public function getUsers(Request $request)
    {
        $query = User::query();

        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    public function createUser(Request $request)
    {
        $validator = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in(['sales', 'factory', 'admin'])],
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
            'is_active' => $request->is_active ?? true,
        ]);

        AuditLog::log('user_created', $user);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $validator = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in(['sales', 'factory', 'admin'])],
            'is_active' => 'boolean',
        ]);

        $oldValues = $user->toArray();
        
        $user->update($validator);

        AuditLog::log('user_updated', $user, $oldValues, $user->toArray());

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function deleteUser(User $user)
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 400);
        }

        $oldValues = $user->toArray();
        $user->delete();

        AuditLog::log('user_deleted', null, $oldValues);

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function toggleUserStatus(User $user)
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Cannot deactivate your own account'], 400);
        }

        $user->update(['is_active' => !$user->is_active]);

        AuditLog::log('user_status_toggled', $user, ['is_active' => !$user->is_active]);

        return response()->json([
            'message' => 'User status updated successfully',
            'is_active' => $user->is_active
        ]);
    }

    // Settings Management
    public function getSettings()
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $validator = $request->validate([
            'default_profit_margin' => 'required|numeric|min:0|max:100',
            'company_name' => 'required|string|max:255',
            'company_address' => 'required|string',
            'company_phone' => 'required|string|max:20',
            'beneficiary_name' => 'nullable|string|max:255',
            'beneficiary_bank' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'bank_address' => 'nullable|string',
        ]);

        foreach ($validator as $key => $value) {
            Setting::set($key, $value);
        }

        AuditLog::log('settings_updated', null, null, $validator);

        return response()->json(['message' => 'Settings updated successfully']);
    }

    // Dashboard Statistics
    public function getDashboardStats()
    {
        $stats = [
            'total_orders' => Order::count(),
            'orders_by_status' => Order::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'total_revenue' => Order::where('payment_confirmed', true)->sum('final_price'),
            'pending_orders' => Order::whereIn('status', ['draft', 'factory_pricing', 'manager_review'])->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'users_by_role' => User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role'),
        ];

        // Monthly statistics for the last 6 months
        $monthlyStats = Order::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as orders, SUM(final_price) as revenue')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $stats['monthly_stats'] = $monthlyStats;

        return response()->json($stats);
    }

    // Audit Logs
    public function getAuditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->action) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }

    // Order Management for Admin
    public function getAllOrders(Request $request)
    {
        $query = Order::with(['customer', 'salesUser', 'factoryUser', 'attachments']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->sales_user_id) {
            $query->where('sales_user_id', $request->sales_user_id);
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

    // Profit Analysis
    public function getProfitAnalysis(Request $request)
    {
        $query = Order::where('payment_confirmed', true);

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->get();

        $analysis = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('final_price'),
            'total_cost' => $orders->sum('factory_cost'),
            'total_profit' => $orders->sum(function($order) {
                return $order->final_price - $order->factory_cost;
            }),
            'average_profit_margin' => $orders->avg('profit_margin_percentage'),
            'profit_by_sales_person' => $orders->groupBy('sales_user_id')->map(function($group) {
                return [
                    'sales_person' => $group->first()->salesUser->name,
                    'orders_count' => $group->count(),
                    'total_revenue' => $group->sum('final_price'),
                    'total_profit' => $group->sum(function($order) {
                        return $order->final_price - $order->factory_cost;
                    }),
                    'average_margin' => $group->avg('profit_margin_percentage'),
                ];
            })->values(),
        ];

        return response()->json($analysis);
    }
}
