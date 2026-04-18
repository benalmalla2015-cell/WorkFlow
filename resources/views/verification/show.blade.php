@extends('layouts.guest')

@section('title', 'التحقق من الوثيقة | WorkFlow')

@section('content')
    <div class="card guest-card" style="max-width: 760px;">
        <div class="card-body p-5">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <div class="display-6 fw-bold text-primary">DAYANCO</div>
                    <div class="text-muted">التحقق من الوثيقة</div>
                </div>
                <span class="badge text-bg-success fs-6">تم التحقق</span>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="border rounded-4 p-4 h-100 bg-light-subtle">
                        <div class="text-muted small mb-2">رقم الطلب</div>
                        <div class="fw-bold fs-4">{{ $order->order_number }}</div>
                        <hr>
                        <div class="text-muted small mb-2">اسم المنتج</div>
                        <div class="fw-semibold">{{ $order->product_name }}</div>
                        <div class="text-muted small mt-3 mb-2">الكمية</div>
                        <div>{{ number_format($order->quantity) }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-4 p-4 h-100 bg-light-subtle">
                        <div class="text-muted small mb-2">الحالة</div>
                        <div class="fw-semibold">{{ $order->status_label }}</div>
                        <div class="text-muted small mt-3 mb-2">اسم موظف المبيعات</div>
                        <div>{{ optional($order->salesUser)->name ?: '—' }}</div>
                        <div class="text-muted small mt-3 mb-2">آخر تحديث</div>
                        <div>{{ optional($order->updated_at)->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4 mb-0">
                تم التحقق من الوثيقة بنجاح من خلال رمز QR المرتبط بالنظام.
            </div>
        </div>
    </div>
@endsection
