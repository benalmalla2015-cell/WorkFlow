@extends('layouts.app')

@section('title', 'طلبات المبيعات | WorkFlow')

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
            <h1 class="h3 mb-1">طلبات المبيعات</h1>
            <div class="text-muted">إدارة الطلبات والمرفقات والمستندات من داخل Laravel.</div>
        </div>
        <a href="{{ route('sales.orders.create') }}" class="btn btn-primary">إضافة طلب جديد</a>
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
                    <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">إعادة</a>
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
                            <th>العميل</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>السعر النهائي</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ optional($order->customer)->full_name ?: '—' }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ number_format($order->quantity) }}</td>
                                <td>{{ $order->final_price ? '$' . number_format((float) $order->final_price, 2) : '—' }}</td>
                                <td>
                                    <span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                                </td>
                                <td>{{ optional($order->created_at)->format('Y-m-d') }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">فتح</a>
                                        @if ($order->status === 'draft')
                                            <form method="POST" action="{{ route('sales.orders.submit', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-dark">إرسال للمصنع</button>
                                            </form>
                                        @endif
                                        @if ($order->status === 'approved')
                                            <form method="POST" action="{{ route('sales.orders.quotation.generate', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">توليد عرض السعر</button>
                                            </form>
                                        @endif
                                        @if ($order->quotation_path)
                                            <a href="{{ route('sales.orders.quotation.download', $order) }}" class="btn btn-sm btn-outline-success">تحميل العرض</a>
                                        @endif
                                        @if (in_array($order->status, ['approved', 'customer_approved', 'completed'], true) && !$order->customer_approval)
                                            <form method="POST" action="{{ route('sales.orders.customer-approval', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info">موافقة العميل</button>
                                            </form>
                                        @endif
                                        @if ($order->status === 'customer_approved' && !$order->payment_confirmed)
                                            <form method="POST" action="{{ route('sales.orders.confirm-payment', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">تأكيد الدفع</button>
                                            </form>
                                        @endif
                                        @if ($order->payment_confirmed || $order->status === 'completed')
                                            <form method="POST" action="{{ route('sales.orders.invoice.generate', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">توليد الفاتورة</button>
                                            </form>
                                        @endif
                                        @if ($order->invoice_path)
                                            <a href="{{ route('sales.orders.invoice.download', $order) }}" class="btn btn-sm btn-outline-danger">تحميل الفاتورة</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">لا توجد طلبات حتى الآن.</td>
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
