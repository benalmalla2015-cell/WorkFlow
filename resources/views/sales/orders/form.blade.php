@extends('layouts.app')

@section('title', ($mode === 'create' ? 'طلب جديد' : 'تعديل الطلب') . ' | WorkFlow')

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
    $canEdit = $mode === 'create' || $order->status === 'draft';
    $isApproved = in_array($order->status, ['approved', 'customer_approved', 'payment_confirmed', 'completed'], true);
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $mode === 'create' ? 'إضافة طلب مبيعات' : 'الطلب ' . $order->order_number }}</h1>
            <div class="text-muted">إدخال بيانات العميل والمنتج والمرفقات وإدارة المستندات.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($mode === 'edit')
                <span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
            @endif
            <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <form method="POST" action="{{ $mode === 'create' ? route('sales.orders.store') : route('sales.orders.update', $order) }}" enctype="multipart/form-data" class="row g-4">
        @csrf
        @if ($mode === 'edit')
            @method('PUT')
        @endif

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">بيانات العميل</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">اسم العميل الكامل</label>
                            <input type="text" name="customer_full_name" class="form-control" value="{{ old('customer_full_name', $order->customer?->full_name) }}" @disabled(!$canEdit) required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم التواصل</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $order->customer?->phone) }}" @disabled(!$canEdit) required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="customer_address" class="form-control" value="{{ old('customer_address', $order->customer?->address) }}" @disabled(!$canEdit) required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $order->customer?->email) }}" @disabled(!$canEdit)>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">بيانات المنتج</h2>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">اسم المنتج</label>
                            <input type="text" name="product_name" class="form-control" value="{{ old('product_name', $order->product_name) }}" @disabled(!$canEdit) required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">الكمية</label>
                            <input type="number" min="1" name="quantity" class="form-control" value="{{ old('quantity', $order->quantity) }}" @disabled(!$canEdit) required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">المواصفات الفنية</label>
                            <textarea name="specifications" rows="4" class="form-control" @disabled(!$canEdit)>{{ old('specifications', $order->specifications) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ملاحظات الطلب</label>
                            <textarea name="customer_notes" rows="3" class="form-control" @disabled(!$canEdit)>{{ old('customer_notes', $order->customer_notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">المرفقات المتعددة</h2>
                    <input type="file" name="attachments[]" class="form-control" multiple @disabled(!$canEdit) accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <div class="form-text">يدعم Word و Excel و PDF والصور حتى 10MB لكل ملف.</div>

                    @if ($mode === 'edit' && $order->attachments->count())
                        <div class="attachment-list mt-3">
                            <div class="fw-semibold mb-2">المرفقات الحالية</div>
                            <div class="list-group list-group-flush">
                                @foreach ($order->attachments->where('type', 'sales_upload') as $attachment)
                                    <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span>{{ $attachment->original_name }}</span>
                                        <span class="text-muted small">{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($canEdit)
            <div class="col-12 d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">{{ $mode === 'create' ? 'إنشاء الطلب' : 'حفظ التعديلات' }}</button>
            </div>
        @endif
    </form>

    @if ($mode === 'edit' && $order->status === 'draft')
        <form method="POST" action="{{ route('sales.orders.submit', $order) }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-outline-dark btn-lg w-100">إرسال إلى المصنع للتسعير</button>
        </form>
    @endif

    @if ($mode === 'edit')
        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="card form-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">الوثائق</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($isApproved)
                                <form method="POST" action="{{ route('sales.orders.quotation.generate', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success">توليد عرض السعر Excel</button>
                                </form>
                            @endif
                            @if ($order->quotation_path)
                                <a href="{{ route('sales.orders.quotation.download', $order) }}" class="btn btn-success">تحميل عرض السعر</a>
                            @endif
                            @if ($order->payment_confirmed || $order->status === 'completed')
                                <form method="POST" action="{{ route('sales.orders.invoice.generate', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">توليد الفاتورة PDF</button>
                                </form>
                            @endif
                            @if ($order->invoice_path)
                                <a href="{{ route('sales.orders.invoice.download', $order) }}" class="btn btn-danger">تحميل الفاتورة</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card form-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">الإجراءات</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($order->status === 'approved' && !$order->customer_approval)
                                <form method="POST" action="{{ route('sales.orders.customer-approval', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-info">تسجيل موافقة العميل</button>
                                </form>
                            @endif
                            @if ($order->status === 'customer_approved' && !$order->payment_confirmed)
                                <form method="POST" action="{{ route('sales.orders.confirm-payment', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning">تأكيد دفع القيمة</button>
                                </form>
                            @endif
                            @if ($order->status === 'factory_pricing')
                                <div class="alert alert-warning mb-0 w-100">الطلب لدى فريق المصنع حالياً لإدخال التسعير.</div>
                            @endif
                            @if ($order->status === 'manager_review')
                                <div class="alert alert-info mb-0 w-100">بانتظار مراجعة المدير واعتماد هامش الربح.</div>
                            @endif
                            @if ($order->status === 'completed')
                                <div class="alert alert-success mb-0 w-100">تم إكمال الطلب بنجاح.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
