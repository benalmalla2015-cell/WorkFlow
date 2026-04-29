@extends('layouts.app')

@section('title', __('تسعير المصنع') . ' | WorkFlow')

@php
    $statusLabels = [
        'draft' => __('جديد'),
        'sent_to_factory' => __('تم الإرسال إلى المصنع'),
        'factory_pricing' => __('عاد لتعديل تشغيلي'),
        'manager_review' => __('قيد مراجعة المدير'),
        'pending_approval' => __('طلب تعديل بانتظار الاعتماد'),
        'approved' => __('معتمد'),
        'customer_approved' => __('موافقة العميل مسجلة'),
        'payment_confirmed' => __('تم تأكيد الدفع'),
        'completed' => __('مكتمل'),
    ];
    $canEdit = auth()->user()?->isAdmin() || (!$order->hasPendingChanges() && $order->status === 'sent_to_factory');
    $showFactoryGuidance = auth()->user()?->isFactory();
    $canRequestAdjustment = !$canEdit && !$order->hasPendingChanges() && $order->canRequestAdjustmentBy(auth()->user());
    $shouldOpenAdjustmentModal = (bool) session('open_adjustment_modal') || old('submission_context') === 'factory_adjustment';
    $factoryItems = collect(old('items', $order->resolvedItems()->map(fn ($item) => [
        'id' => $item->id,
        'item_name' => $item->item_name,
        'quantity' => $item->quantity,
        'description' => $item->description,
        'supplier_name' => $item->supplier_name,
        'product_code' => $item->product_code,
        'unit_cost' => $item->unit_cost,
    ])->values()->all()));
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('تسعير الطلب') }} {{ $order->order_number }}</h1>
            <div class="text-muted">{{ __('إدخال بيانات المورد والتكلفة ومدة الإنتاج.') }}</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
            <a href="{{ route('factory.orders.index') }}" class="btn btn-outline-secondary">{{ __('رجوع') }}</a>
        </div>
    </div>

    @if ($showFactoryGuidance)
        <div class="alert alert-warning border-0 shadow-sm">{{ __('تنبيه: يرجى التقيد بالمواصفات الفنية المرفقة عند التنفيذ.') }}</div>
    @endif

    @if ($order->status === 'sent_to_factory' && $canEdit)
        <div class="alert alert-primary border-0 shadow-sm">{{ __('هذا هو الإدخال الأولي لتسعير المصنع. بعد إرسال التسعير للإدارة ستصبح أي تعديلات لاحقة عبر طلب تعديل رسمي فقط.') }}</div>
    @endif

    @if ($order->hasPendingChanges())
        <div class="alert alert-warning border-0 shadow-sm">{{ __('يوجد تعديل معلّق لهذا الطلب بانتظار اعتماد المدير، لذا تم تعطيل أي تعديل إضافي مؤقتًا.') }}</div>
    @endif

    @if (!$canEdit && !$order->hasPendingChanges())
        <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="fw-semibold mb-1">{{ __('الحقول الأصلية مقفلة') }}</div>
                <div>{{ __('لا يمكن تعديل السجل الأصلي مباشرة في هذه المرحلة. استخدم طلب تعديل رسمي لإرسال التغييرات إلى الإدارة.') }}</div>
            </div>
            @if ($canRequestAdjustment)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#factoryAdjustmentModal">{{ __('طلب تعديل') }}</button>
            @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card form-card mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('بيانات المنتج') }}</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('المنتج') }}</dt>
                        <dd class="col-sm-8">{{ $order->product_name }}</dd>
                        <dt class="col-sm-4">{{ __('الكمية') }}</dt>
                        <dd class="col-sm-8">{{ number_format($order->quantity) }} {{ __('PCS') }}</dd>
                        <dt class="col-sm-4">{{ __('المواصفات') }}</dt>
                        <dd class="col-sm-8">{{ $order->specifications ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('عدد العناصر') }}</dt>
                        <dd class="col-sm-8">{{ $factoryItems->count() }}</dd>
                        <dt class="col-sm-4">{{ __('إجمالي تكلفة المصنع الحالية') }}</dt>
                        <dd class="col-sm-8">{{ number_format((float) ($pricingSummary['total_factory_cost'] ?? 0), 2) }} {{ __('USD') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('مرفقات مرجعية من المبيعات') }}</h2>
                    <div class="attachment-list list-group list-group-flush">
                        @forelse ($order->attachments->where('type', 'sales_upload') as $attachment)
                            <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>{{ $attachment->original_name }}</span>
                                <span class="text-muted small">{{ __('مرجعي') }}</span>
                            </a>
                        @empty
                            <div class="text-muted">{{ __('لا توجد مرفقات مرجعية.') }}</div>
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
                        <h2 class="h5 section-title">{{ __('تسعير عناصر الطلب') }}</h2>
                        <div class="alert alert-light border mb-3">{{ __('يجب تعبئة المورد وكود المنتج وسعر الوحدة لكل عنصر قبل الإرسال إلى مراجعة المدير.') }}</div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 18%;">{{ __('العنصر') }}</th>
                                        <th style="width: 10%;">{{ __('الكمية') }}</th>
                                        <th>{{ __('الوصف') }}</th>
                                        <th style="width: 18%;">{{ __('اسم المورد') }}</th>
                                        <th style="width: 16%;">{{ __('كود المنتج') }}</th>
                                        <th style="width: 16%;">{{ __('سعر الوحدة USD') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($factoryItems as $index => $item)
                                        <tr>
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
                                                <div class="fw-semibold">{{ $item['item_name'] ?? '—' }}</div>
                                            </td>
                                            <td>{{ number_format((int) ($item['quantity'] ?? 1)) }}</td>
                                            <td>{{ $item['description'] ?? '—' }}</td>
                                            <td>
                                                <input type="text" name="items[{{ $index }}][supplier_name]" class="form-control" value="{{ $item['supplier_name'] ?? '' }}" @readonly(!$canEdit) required>
                                            </td>
                                            <td>
                                                <input type="text" name="items[{{ $index }}][product_code]" class="form-control" value="{{ $item['product_code'] ?? '' }}" @readonly(!$canEdit) required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][unit_cost]" class="form-control" value="{{ $item['unit_cost'] ?? '' }}" @readonly(!$canEdit) required>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('مدة الإنتاج بالأيام') }}</label>
                                <input type="number" min="1" name="production_days" class="form-control" value="{{ old('production_days', $order->production_days) }}" @readonly(!$canEdit) required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card form-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">{{ __('مرفقات المصنع') }}</h2>
                        <input type="file" name="attachments[]" class="form-control" multiple @readonly(!$canEdit) accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">{{ __('يمكنك رفع عروض المورد أو ملفات المواصفات أو الصور.') }}</div>

                        <div class="attachment-list list-group list-group-flush mt-3">
                            @forelse ($order->attachments->where('type', 'factory_upload') as $attachment)
                                <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>{{ $attachment->original_name }}</span>
                                    <span class="text-muted small">{{ __('مصنع') }}</span>
                                </a>
                            @empty
                                <div class="text-muted">{{ __('لا توجد مرفقات مصنع مضافة بعد.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($canEdit)
                    <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('إرسال التسعير لمراجعة المدير') }}</button>
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
                            <h2 class="h5 mb-1">{{ __('طلب تعديل تشغيلي للطلب') }} {{ $order->order_number }}</h2>
                            <div class="text-muted small">{{ __('سيُرسل التعديل إلى الإدارة لاعتماده قبل تحديث بيانات المصنع الأصلية.') }}</div>
                        </div>
                        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('factory.orders.adjustments.store', $order) }}" enctype="multipart/form-data" id="factory-adjustment-form">
                            @csrf
                            <input type="hidden" name="submission_context" value="factory_adjustment">

                            <div class="card form-card mb-4">
                                <div class="card-body p-4">
                                    <h3 class="h5 section-title">{{ __('ملخص الطلب') }}</h3>
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">{{ __('المنتج') }}</dt>
                                        <dd class="col-sm-8">{{ $order->product_name }}</dd>
                                        <dt class="col-sm-4">{{ __('الكمية') }}</dt>
                                        <dd class="col-sm-8">{{ number_format($order->quantity) }} {{ __('PCS') }}</dd>
                                        <dt class="col-sm-4">{{ __('المواصفات') }}</dt>
                                        <dd class="col-sm-8">{{ $order->specifications ?: '—' }}</dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="card form-card mb-4">
                                <div class="card-body p-4">
                                    <h3 class="h5 section-title">{{ __('بيانات العناصر المقترحة') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width: 18%;">{{ __('العنصر') }}</th>
                                                    <th style="width: 10%;">{{ __('الكمية') }}</th>
                                                    <th>{{ __('الوصف') }}</th>
                                                    <th style="width: 18%;">{{ __('اسم المورد') }}</th>
                                                    <th style="width: 16%;">{{ __('كود المنتج') }}</th>
                                                    <th style="width: 16%;">{{ __('سعر الوحدة USD') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($factoryItems as $index => $item)
                                                    <tr>
                                                        <td>
                                                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
                                                            <div class="fw-semibold">{{ $item['item_name'] ?? '—' }}</div>
                                                        </td>
                                                        <td>{{ number_format((int) ($item['quantity'] ?? 1)) }}</td>
                                                        <td>{{ $item['description'] ?? '—' }}</td>
                                                        <td>
                                                            <input type="text" name="items[{{ $index }}][supplier_name]" class="form-control" value="{{ $item['supplier_name'] ?? '' }}" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" name="items[{{ $index }}][product_code]" class="form-control" value="{{ $item['product_code'] ?? '' }}" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][unit_cost]" class="form-control" value="{{ $item['unit_cost'] ?? '' }}" required>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="row g-3 mt-2">
                                        <div class="col-md-6">
                                            <label class="form-label">{{ __('مدة الإنتاج بالأيام') }}</label>
                                            <input type="number" min="1" name="production_days" class="form-control" value="{{ old('production_days', $order->production_days) }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card form-card mb-4">
                                <div class="card-body p-4">
                                    <h3 class="h5 section-title">{{ __('مرفقات داعمة') }}</h3>
                                    <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                    <div class="form-text">{{ __('يمكنك رفع عرض مورد جديد أو صور أو ملفات فنية محدثة.') }}</div>

                                    <div class="attachment-list list-group list-group-flush mt-3">
                                        @forelse ($order->attachments->where('type', 'factory_upload') as $attachment)
                                            <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                <span>{{ $attachment->original_name }}</span>
                                                <span class="text-muted small">{{ __('مصنع') }}</span>
                                            </a>
                                        @empty
                                            <div class="text-muted">{{ __('لا توجد مرفقات مصنع حالية.') }}</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                        <button type="submit" form="factory-adjustment-form" class="btn btn-primary">{{ __('إرسال طلب التعديل للاعتماد') }}</button>
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
