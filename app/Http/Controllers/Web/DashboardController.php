<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isFactory()) {
            return redirect()->route('factory.orders.index');
        }

        return redirect()->route('sales.orders.index');
    }

    public function verifyOrder(string $orderNumber)
    {
        $order = Order::withoutGlobalScopes()
            ->with(['salesUser'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('verification.show', [
            'order' => $order,
        ]);
    }
}
