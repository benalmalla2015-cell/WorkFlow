@extends('layouts.app')

@section('title', 'طلبات المصنع | WorkFlow')

@php
    $showAdminPricing = auth()->user()?->isAdmin();
    $statusLabels = [
        'sent_to_factory' => 'تم الإرسال إلى المصنع',
        'factory_pricing' => 'عاد لتعديل تشغيلي',
        'manager_review' => 'قيد مراجعة المدير',
        'pending_approval' => 'طلب تعديل بانتظار الاعتماد',
        'approved' => 'معتمد',
        'customer_approved' => 'موافقة العميل مسجلة',
        'payment_confirmed' => 'تم تأكيد الدفع',
        'completed' => 'مكتمل',
    ];
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">طلبات المصنع</h1>
        <div class="text-muted">متابعة طلبات التنفيذ والمواصفات الفنية والمرفقات المرتبطة بها.</div>
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
                            @if ($showAdminPricing)
                                <th>تكلفة المصنع</th>
                            @endif
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
                                @if ($showAdminPricing)
                                    <td>{{ $order->factory_cost ? '$' . number_format((float) $order->factory_cost, 2) : '—' }}</td>
                                @endif
                                <td>{{ $order->production_days ? $order->production_days . ' يوم' : '—' }}</td>
                                <td><span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('factory.orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">فتح</a>
                                        @if (!$order->isDraft() && !$order->hasPendingChanges() && $order->canRequestAdjustmentBy(auth()->user()))
                                            <a href="{{ route('factory.orders.adjustments.create', $order) }}" class="btn btn-sm btn-outline-primary">طلب تعديل</a>
                                        @endif
                                        @if ($order->status === 'pending_approval')
                                            <span class="badge text-bg-warning">بانتظار اعتماد التعديل</span>
                                        @endif
                                        @if ($order->status === 'manager_review')
                                            <span class="badge text-bg-warning">بانتظار المدير</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showAdminPricing ? 8 : 7 }}" class="text-center py-5 text-muted">لا توجد طلبات متاحة حالياً.</td>
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
