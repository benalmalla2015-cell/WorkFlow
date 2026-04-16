@extends('layouts.app')

@section('title', 'طلب تعديل مصنع | WorkFlow')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">طلب تعديل تشغيلي للطلب {{ $order->order_number }}</h1>
            <div class="text-muted">سيُرسل التعديل إلى الإدارة لاعتماده قبل تحديث بيانات المصنع الأصلية.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            <a href="{{ route('factory.orders.edit', $order) }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm">
        استخدم هذا النموذج فقط عندما تحتاج تعديل بيانات المورد أو التكلفة أو مدة الإنتاج بعد انتهاء الإدخال الأولي وإرسال الطلب للإدارة.
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card form-card mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">ملخص الطلب</h2>
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
                    <h2 class="h5 section-title">مرفقات مرجعية</h2>
                    <div class="attachment-list list-group list-group-flush">
                        @forelse ($order->attachments->where('type', 'sales_upload') as $attachment)
                            <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>{{ $attachment->original_name }}</span>
                                <span class="text-muted small">مرجعي</span>
                            </a>
                        @empty
                            <div class="text-muted">لا توجد مرفقات مرجعية.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <form method="POST" action="{{ route('factory.orders.adjustments.store', $order) }}" enctype="multipart/form-data">
                @csrf

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">بيانات المورد المقترحة</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المورد</label>
                                <input type="text" name="supplier_name" class="form-control" value="{{ old('supplier_name', $order->supplier_name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">كود المنتج</label>
                                <input type="text" name="product_code" class="form-control" value="{{ old('product_code', $order->product_code) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تكلفة المصنع USD</label>
                                <input type="number" step="0.01" min="0" name="factory_cost" class="form-control" value="{{ old('factory_cost', $order->factory_cost) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مدة الإنتاج بالأيام</label>
                                <input type="number" min="1" name="production_days" class="form-control" value="{{ old('production_days', $order->production_days) }}" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">مرفقات داعمة</h2>
                        <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">يمكنك رفع عرض مورد جديد أو صور أو ملفات فنية محدثة.</div>

                        <div class="attachment-list list-group list-group-flush mt-3">
                            @forelse ($order->attachments->where('type', 'factory_upload') as $attachment)
                                <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>{{ $attachment->original_name }}</span>
                                    <span class="text-muted small">مصنع</span>
                                </a>
                            @empty
                                <div class="text-muted">لا توجد مرفقات مصنع حالية.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">إرسال طلب التعديل للاعتماد</button>
            </form>
        </div>
    </div>
@endsection
