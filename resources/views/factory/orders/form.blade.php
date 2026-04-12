@extends('layouts.app')

@section('title', 'تسعير المصنع | WorkFlow')

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
    $canEdit = $order->status === 'factory_pricing';
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">تسعير الطلب {{ $order->order_number }}</h1>
            <div class="text-muted">إدخال بيانات المورد والتكلفة ومدة الإنتاج.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
            <a href="{{ route('factory.orders.index') }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        لا يسمح لهذه الشاشة بإظهار بيانات العميل أو عنوانه أو ملاحظاته. المعروض هنا يقتصر على المواصفات الفنية والمرفقات المسموح بها.
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card form-card mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">بيانات المنتج</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">المنتج</dt>
                        <dd class="col-sm-8">{{ $order->product_name }}</dd>
                        <dt class="col-sm-4">الكمية</dt>
                        <dd class="col-sm-8">{{ number_format($order->quantity) }} PCS</dd>
                        <dt class="col-sm-4">المواصفات</dt>
                        <dd class="col-sm-8">{{ $order->specifications ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">مرفقات مرجعية من المبيعات</h2>
                    <div class="attachment-list list-group list-group-flush">
                        @forelse ($order->attachments->where('type', 'sales_upload') as $attachment)
                            <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>{{ $attachment->original_name }}</span>
                                <span class="text-muted small">Reference</span>
                            </a>
                        @empty
                            <div class="text-muted">لا توجد مرفقات مرجعية.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <form method="POST" action="{{ route('factory.orders.update', $order) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">بيانات المورد والتكلفة</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المورد</label>
                                <input type="text" name="supplier_name" class="form-control" value="{{ old('supplier_name', $order->supplier_name) }}" @disabled(!$canEdit) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">كود المنتج</label>
                                <input type="text" name="product_code" class="form-control" value="{{ old('product_code', $order->product_code) }}" @disabled(!$canEdit) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تكلفة المصنع USD</label>
                                <input type="number" step="0.01" min="0" name="factory_cost" class="form-control" value="{{ old('factory_cost', $order->factory_cost) }}" @disabled(!$canEdit) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مدة الإنتاج بالأيام</label>
                                <input type="number" min="1" name="production_days" class="form-control" value="{{ old('production_days', $order->production_days) }}" @disabled(!$canEdit) required>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info mb-0">سيتم تطبيق هامش ربح افتراضي {{ $defaultMargin }}% تلقائياً ثم إرسال الطلب إلى المدير للمراجعة.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">مرفقات المصنع</h2>
                        <input type="file" name="attachments[]" class="form-control" multiple @disabled(!$canEdit) accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">يمكنك رفع عروض المورد أو ملفات المواصفات أو الصور.</div>

                        <div class="attachment-list list-group list-group-flush mt-3">
                            @forelse ($order->attachments->where('type', 'factory_upload') as $attachment)
                                <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>{{ $attachment->original_name }}</span>
                                    <span class="text-muted small">Factory</span>
                                </a>
                            @empty
                                <div class="text-muted">لا توجد مرفقات مصنع مضافة بعد.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($canEdit)
                    <button type="submit" class="btn btn-primary btn-lg w-100">إرسال التسعير لمراجعة المدير</button>
                @else
                    <div class="alert alert-secondary mb-0">هذا الطلب في حالة {{ $statusLabels[$order->status] ?? $order->status }} ولا يمكن تعديله حالياً.</div>
                @endif
            </form>
        </div>
    </div>
@endsection
