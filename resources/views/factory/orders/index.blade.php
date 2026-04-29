@extends('layouts.app')

@section('title', __('طلبات المصنع') . ' | WorkFlow')

@php
    $showAdminPricing = auth()->user()?->isAdmin();
    $statusLabels = [
        'sent_to_factory' => __('تم الإرسال إلى المصنع'),
        'factory_pricing' => __('عاد لتعديل تشغيلي'),
        'manager_review' => __('قيد مراجعة المدير'),
        'pending_approval' => __('طلب تعديل بانتظار الاعتماد'),
        'approved' => __('معتمد'),
        'customer_approved' => __('موافقة العميل مسجلة'),
        'payment_confirmed' => __('تم تأكيد الدفع'),
        'completed' => __('مكتمل'),
    ];
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">{{ __('طلبات المصنع') }}</h1>
        <div class="text-muted">{{ __('متابعة طلبات التنفيذ والمواصفات الفنية والمرفقات المرتبطة بها.') }}</div>
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
                    <a href="{{ route('factory.orders.index') }}" class="btn btn-outline-secondary">{{ __('إعادة') }}</a>
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
                            <th>{{ __('المنتج') }}</th>
                            <th>{{ __('الكمية') }}</th>
                            <th>{{ __('المواصفات') }}</th>
                            @if ($showAdminPricing)
                                <th>{{ __('تكلفة المصنع') }}</th>
                            @endif
                            <th>{{ __('مدة الإنتاج') }}</th>
                            <th>{{ __('الحالة') }}</th>
                            <th>{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ number_format($order->quantity) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($order->specifications, 80) ?: '—' }}</td>
                                @if ($showAdminPricing)
                                    <td>{{ $order->factory_cost ? '$' . number_format((float) $order->factory_cost, 2) : '—' }}</td>
                                @endif
                                <td>{{ $order->production_days ? $order->production_days . ' ' . __('يوم') : '—' }}</td>
                                <td><span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('factory.orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">{{ __('فتح') }}</a>
                                        @if (!$order->isDraft() && !$order->hasPendingChanges() && $order->canRequestAdjustmentBy(auth()->user()))
                                            <a href="{{ route('factory.orders.adjustments.create', $order) }}" class="btn btn-sm btn-outline-primary">{{ __('طلب تعديل') }}</a>
                                        @endif
                                        @if ($order->status === 'pending_approval')
                                            <span class="badge text-bg-warning">{{ __('بانتظار اعتماد التعديل') }}</span>
                                        @endif
                                        @if ($order->status === 'manager_review')
                                            <span class="badge text-bg-warning">{{ __('بانتظار المدير') }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showAdminPricing ? 8 : 7 }}" class="text-center py-5 text-muted">{{ __('لا توجد طلبات متاحة حالياً.') }}</td>
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
