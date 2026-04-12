@extends('layouts.app')

@section('title', 'طلبات المصنع | WorkFlow')

@php
    $statusLabels = [
        'factory_pricing' => 'Factory Pricing',
        'manager_review' => 'Manager Review',
        'approved' => 'Approved',
        'customer_approved' => 'Customer Approved',
        'payment_confirmed' => 'Payment Confirmed',
        'completed' => 'Completed',
    ];
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">طلبات المصنع</h1>
        <div class="text-muted">واجهة التسعير والمرفقات مع خصوصية كاملة لبيانات العميل.</div>
    </div>

    <div class="alert alert-warning border-0 shadow-sm mb-4">
        لا تظهر هنا أي بيانات تخص العميل أو عنوانه أو ملاحظاته الشخصية. تظهر فقط المواصفات الفنية والمرفقات المسموح بها.
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark flex-fill">تصفية</button>
                    <a href="{{ route('factory.orders.index') }}" class="btn btn-outline-secondary">إعادة</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>المواصفات</th>
                            <th>تكلفة المصنع</th>
                            <th>مدة الإنتاج</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ number_format($order->quantity) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($order->specifications, 80) ?: '—' }}</td>
                                <td>{{ $order->factory_cost ? '$' . number_format((float) $order->factory_cost, 2) : '—' }}</td>
                                <td>{{ $order->production_days ? $order->production_days . ' يوم' : '—' }}</td>
                                <td><span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('factory.orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">فتح</a>
                                        @if ($order->status === 'manager_review')
                                            <span class="badge text-bg-warning">بانتظار المدير</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">لا توجد طلبات متاحة حالياً.</td>
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
