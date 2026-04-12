@extends('layouts.app')

@section('title', 'لوحة الإدارة | WorkFlow')

@php
    $statusLabels = [
        'draft' => 'Draft',
        'factory_pricing' => 'Factory Pricing',
        'manager_review' => 'Manager Review',
        'approved' => 'Approved',
        'customer_approved' => 'Customer Approved',
        'payment_confirmed' => 'Payment Confirmed',
        'completed' => 'Completed',
    ];
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">لوحة الإدارة</h1>
            <div class="text-muted">مراجعة الطلبات والأرباح وأداء الموظفين داخل Laravel.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-dark">تصفية</button>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">إجمالي الطلبات</div>
                    <div class="display-6 fw-bold">{{ $stats['total_orders'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">بانتظار الإجراء</div>
                    <div class="display-6 fw-bold text-warning">{{ $stats['pending_orders'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">إيراد محقق</div>
                    <div class="display-6 fw-bold text-success">${{ number_format($stats['total_revenue'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">المستخدمون النشطون</div>
                    <div class="display-6 fw-bold">{{ $stats['active_users'] }}/{{ $stats['total_users'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card page-card chart-card h-100">
                <div class="card-body">
                    <h2 class="h5 section-title">اتجاه الأرباح الشهرية</h2>
                    <canvas id="profitChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card page-card chart-card h-100">
                <div class="card-body">
                    <h2 class="h5 section-title">توزيع الطلبات حسب الحالة</h2>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 section-title">طلبات بانتظار اعتماد المدير</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المنتج</th>
                            <th>تكلفة المصنع</th>
                            <th>الهامش الافتراضي</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingApprovals as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ optional($order->customer)->full_name ?: '—' }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ $order->factory_cost ? '$' . number_format((float) $order->factory_cost, 2) : '—' }}</td>
                                <td>{{ number_format($stats['default_profit_margin'], 2) }}%</td>
                                <td>
                                    <a href="{{ route('admin.orders.review', $order) }}" class="btn btn-sm btn-primary">مراجعة واعتماد</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">لا توجد طلبات معلقة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 section-title">أداء موظفي المبيعات</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>عدد الطلبات</th>
                            <th>الإيراد التقديري</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($profitAnalysis['employee_performance'] as $employee)
                            <tr>
                                <td class="fw-semibold">{{ $employee['name'] }}</td>
                                <td>{{ $employee['orders_count'] }}</td>
                                <td>${{ number_format($employee['revenue'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>تكلفة المصنع</th>
                            <th>السعر النهائي</th>
                            <th>الهامش</th>
                            <th>الحالة</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ optional($order->customer)->full_name ?: '—' }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ number_format($order->quantity) }}</td>
                                <td>{{ $order->factory_cost ? '$' . number_format((float) $order->factory_cost, 2) : '—' }}</td>
                                <td>{{ $order->final_price ? '$' . number_format((float) $order->final_price, 2) : '—' }}</td>
                                <td>{{ $order->profit_margin_percentage ? number_format((float) $order->profit_margin_percentage, 2) . '%' : '—' }}</td>
                                <td><span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                                <td>
                                    @if ($order->status === 'manager_review')
                                        <a href="{{ route('admin.orders.review', $order) }}" class="btn btn-sm btn-outline-primary">مراجعة</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">لا توجد بيانات حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const profitContext = document.getElementById('profitChart');
        const statusContext = document.getElementById('statusChart');

        if (profitContext) {
            new Chart(profitContext, {
                type: 'line',
                data: {
                    labels: @json($profitAnalysis['monthly_labels']),
                    datasets: [{
                        label: 'Monthly Profit (USD)',
                        data: @json($profitAnalysis['monthly_profit']),
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.12)',
                        fill: true,
                        tension: 0.35,
                    }],
                },
            });
        }

        if (statusContext) {
            new Chart(statusContext, {
                type: 'doughnut',
                data: {
                    labels: @json(array_keys($stats['orders_by_status']->toArray())),
                    datasets: [{
                        data: @json(array_values($stats['orders_by_status']->toArray())),
                        backgroundColor: ['#94a3b8', '#60a5fa', '#fbbf24', '#4ade80', '#22d3ee', '#10b981', '#8b5cf6'],
                    }],
                },
            });
        }
    </script>
@endpush
