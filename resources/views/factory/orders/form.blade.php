@extends('layouts.app')

@section('title', 'تسعير المصنع | WorkFlow')

@php
    $statusLabels = [
        'draft' => 'جديد',
        'sent_to_factory' => 'تم الإرسال إلى المصنع',
        'factory_pricing' => 'عاد لتعديل تشغيلي',
        'manager_review' => 'قيد مراجعة المدير',
        'pending_approval' => 'طلب تعديل بانتظار الاعتماد',
        'approved' => 'معتمد',
        'customer_approved' => 'موافقة العميل مسجلة',
        'payment_confirmed' => 'تم تأكيد الدفع',
        'completed' => 'مكتمل',
    ];
    $canEdit = auth()->user()?->isAdmin() || (!$order->hasPendingChanges() && $order->status === 'sent_to_factory');
    $showFactoryGuidance = auth()->user()?->isFactory();
    $canRequestAdjustment = !$canEdit && !$order->hasPendingChanges() && $order->canRequestAdjustmentBy(auth()->user());
    $shouldOpenAdjustmentModal = (bool) session('open_adjustment_modal') || old('submission_context') === 'factory_adjustment';
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

    @if ($showFactoryGuidance)
        <div class="alert alert-warning border-0 shadow-sm">تنبيه: يرجى التقيد بالمواصفات الفنية المرفقة عند التنفيذ.</div>
    @endif

    @if ($order->status === 'sent_to_factory' && $canEdit)
        <div class="alert alert-primary border-0 shadow-sm">هذا هو الإدخال الأولي لتسعير المصنع. بعد إرسال التسعير للإدارة ستصبح أي تعديلات لاحقة عبر طلب تعديل رسمي فقط.</div>
    @endif

    @if ($order->hasPendingChanges())
        <div class="alert alert-warning border-0 shadow-sm">يوجد تعديل معلّق لهذا الطلب بانتظار اعتماد المدير، لذا تم تعطيل أي تعديل إضافي مؤقتًا.</div>
    @endif

    @if (!$canEdit && !$order->hasPendingChanges())
        <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="fw-semibold mb-1">الحقول الأصلية مقفلة</div>
                <div>لا يمكن تعديل السجل الأصلي مباشرة في هذه المرحلة. استخدم طلب تعديل رسمي لإرسال التغييرات إلى الإدارة.</div>
            </div>
            @if ($canRequestAdjustment)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#factoryAdjustmentModal">طلب تعديل</button>
            @endif
        </div>
    @endif

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
            <form method="POST" action="{{ route('factory.orders.update', $order) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">بيانات المورد والتكلفة</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المورد</label>
                                <input type="text" name="supplier_name" class="form-control" value="{{ old('supplier_name', $order->supplier_name) }}" @readonly(!$canEdit) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">كود المنتج</label>
                                <input type="text" name="product_code" class="form-control" value="{{ old('product_code', $order->product_code) }}" @readonly(!$canEdit) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تكلفة المصنع USD</label>
                                <input type="number" step="0.01" min="0" name="factory_cost" class="form-control" value="{{ old('factory_cost', $order->factory_cost) }}" @readonly(!$canEdit) required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مدة الإنتاج بالأيام</label>
                                <input type="number" min="1" name="production_days" class="form-control" value="{{ old('production_days', $order->production_days) }}" @readonly(!$canEdit) required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">مرفقات المصنع</h2>
                        <input type="file" name="attachments[]" class="form-control" multiple @readonly(!$canEdit) accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">يمكنك رفع عروض المورد أو ملفات المواصفات أو الصور.</div>

                        <div class="attachment-list list-group list-group-flush mt-3">
                            @forelse ($order->attachments->where('type', 'factory_upload') as $attachment)
                                <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>{{ $attachment->original_name }}</span>
                                    <span class="text-muted small">مصنع</span>
                                </a>
                            @empty
                                <div class="text-muted">لا توجد مرفقات مصنع مضافة بعد.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($canEdit)
                    <button type="submit" class="btn btn-primary btn-lg w-100">إرسال التسعير لمراجعة المدير</button>
                @endif
            </form>
        </div>
    </div>

    @if ($canRequestAdjustment)
        <div class="modal fade" id="factoryAdjustmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <div>
                            <h2 class="h5 mb-1">طلب تعديل تشغيلي للطلب {{ $order->order_number }}</h2>
                            <div class="text-muted small">سيُرسل التعديل إلى الإدارة لاعتماده قبل تحديث بيانات المصنع الأصلية.</div>
                        </div>
                        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('factory.orders.adjustments.store', $order) }}" enctype="multipart/form-data" id="factory-adjustment-form">
                            @csrf
                            <input type="hidden" name="submission_context" value="factory_adjustment">

                            <div class="card form-card mb-4">
                                <div class="card-body p-4">
                                    <h3 class="h5 section-title">ملخص الطلب</h3>
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

                            <div class="card form-card mb-4">
                                <div class="card-body p-4">
                                    <h3 class="h5 section-title">بيانات المورد المقترحة</h3>
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
                                    <h3 class="h5 section-title">مرفقات داعمة</h3>
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
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" form="factory-adjustment-form" class="btn btn-primary">إرسال طلب التعديل للاعتماد</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($canRequestAdjustment && $shouldOpenAdjustmentModal)
        @push('scripts')
            <script>
                const factoryAdjustmentModalElement = document.getElementById('factoryAdjustmentModal');
                if (factoryAdjustmentModalElement) {
                    bootstrap.Modal.getOrCreateInstance(factoryAdjustmentModalElement).show();
                }
            </script>
        @endpush
    @endif
@endsection
