@extends('layouts.app')

@section('title', __('طلبات المبيعات') . ' | WorkFlow')

@php
    $showAdminPricing = auth()->user()?->isAdmin();
    $statusLabels = [
        'draft' => __('جديد'),
        'sent_to_factory' => __('تم الإرسال إلى المصنع'),
        'factory_pricing' => __('طلب عائد لتعديل تشغيلي'),
        'manager_review' => __('قيد مراجعة المدير'),
        'pending_approval' => __('طلب تعديل بانتظار الاعتماد'),
        'approved' => __('معتمد'),
        'customer_approved' => __('موافقة العميل مسجلة'),
        'payment_confirmed' => __('تم تأكيد الدفع'),
        'completed' => __('مكتمل'),
    ];
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('طلبات المبيعات') }}</h1>
            <div class="text-muted">{{ __('إدارة الطلبات والمرفقات والمستندات المؤسسية من داخل النظام.') }}</div>
        </div>
        <a href="{{ route('sales.orders.create') }}" class="btn btn-primary">{{ __('إضافة طلب جديد') }}</a>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">{{ __('الحالة') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('الكل') }}</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('من تاريخ') }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('إلى تاريخ') }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark flex-fill">{{ __('تصفية') }}</button>
                    <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">{{ __('إعادة') }}</a>
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
                            <th>{{ __('رقم الطلب') }}</th>
                            <th>{{ __('العميل') }}</th>
                            <th>{{ __('المنتج') }}</th>
                            <th>{{ __('الكمية') }}</th>
                            @if ($showAdminPricing)
                                <th>{{ __('السعر النهائي') }}</th>
                            @endif
                            <th>{{ __('الحالة') }}</th>
                            <th>{{ __('التاريخ') }}</th>
                            <th>{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $documentsEnabled = $order->canGenerateCommercialDocuments();
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ optional($order->customer)->full_name ?: '—' }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ number_format($order->quantity) }}</td>
                                @if ($showAdminPricing)
                                    <td>{{ $order->final_price ? '$' . number_format((float) $order->final_price, 2) : '—' }}</td>
                                @endif
                                <td>
                                    <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
                                </td>
                                <td>{{ optional($order->created_at)->format('Y-m-d') }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">{{ __('فتح') }}</a>
                                        @if ($order->status === 'pending_approval')
                                            <span class="badge text-bg-warning">{{ __('بانتظار اعتماد التعديل') }}</span>
                                        @endif
                                        @if ($order->status === 'draft')
                                            <form method="POST" action="{{ route('sales.orders.submit', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-dark">{{ __('إرسال للمصنع') }}</button>
                                            </form>
                                        @endif
                                        @if (!$order->isDraft() && !$order->hasPendingChanges() && $order->canRequestAdjustmentBy(auth()->user()))
                                            <a href="{{ route('sales.orders.adjustments.create', $order) }}" class="btn btn-sm btn-outline-primary">{{ __('طلب تعديل') }}</a>
                                        @endif
                                        @if ($documentsEnabled)
                                            <form method="POST" action="{{ route('sales.orders.quotation.generate', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">{{ __('توليد عرض سعر PDF') }}</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-success" disabled>{{ __('توليد عرض سعر PDF') }}</button>
                                        @endif
                                        @if ($order->quotation_path)
                                            <a href="{{ route('sales.orders.quotation.download', $order) }}" class="btn btn-sm btn-outline-success">{{ __('تحميل عرض السعر PDF') }}</a>
                                        @endif
                                        @if (in_array($order->status, ['approved', 'customer_approved', 'completed'], true) && !$order->customer_approval)
                                            <form method="POST" action="{{ route('sales.orders.customer-approval', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info">{{ __('موافقة العميل') }}</button>
                                            </form>
                                        @endif
                                        @if ($order->status === 'customer_approved' && !$order->payment_confirmed)
                                            <form method="POST" action="{{ route('sales.orders.confirm-payment', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('تأكيد الدفع') }}</button>
                                            </form>
                                        @endif
                                        @if ($documentsEnabled)
                                            <form method="POST" action="{{ route('sales.orders.invoice.generate', $order) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('توليد فاتورة PDF') }}</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled>{{ __('توليد فاتورة PDF') }}</button>
                                        @endif
                                        @if ($order->invoice_path)
                                            <a href="{{ route('sales.orders.invoice.download', $order) }}" class="btn btn-sm btn-outline-danger">{{ __('تحميل الفاتورة PDF') }}</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showAdminPricing ? 8 : 7 }}" class="text-center py-5 text-muted">{{ __('لا توجد طلبات حتى الآن.') }}</td>
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
