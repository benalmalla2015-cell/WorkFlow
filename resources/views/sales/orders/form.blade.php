@extends('layouts.app')

@section('title', ($mode === 'create' ? __('طلب جديد') : __('تعديل الطلب')) . ' | WorkFlow')

@php
    $canEdit = $mode === 'create' || ($mode === 'edit' && $order->canBeEditedBy(auth()->user()));
    $showOrderGuidance = !auth()->user()?->isAdmin();
    $canRequestAdjustment = $mode === 'edit'
        && !$canEdit
        && !$order->hasPendingChanges()
        && $order->canRequestAdjustmentBy(auth()->user());
    $shouldOpenAdjustmentModal = (bool) session('open_adjustment_modal') || old('submission_context') === 'sales_adjustment';
    $lineItems = collect(old('items', $order->resolvedItems()->map(fn ($item) => [
        'item_name' => $item->item_name,
        'quantity' => $item->quantity,
        'description' => $item->description,
    ])->values()->all()));
    if ($lineItems->isEmpty()) {
        $lineItems = collect([['item_name' => '', 'quantity' => 1, 'description' => '']]);
    }
    $documentsEnabled = $mode === 'edit' && $order->canGenerateCommercialDocuments();
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $mode === 'create' ? __('إضافة طلب مبيعات') : __('الطلب') . ' ' . $order->order_number }}</h1>
            <div class="text-muted">{{ __('إدخال بيانات العميل وعناصر الطلب والمرفقات وإدارة مستندات PDF الرسمية.') }}</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($mode === 'edit')
                <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            @endif
            <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">{{ __('رجوع') }}</a>
        </div>
    </div>

    @if ($mode === 'edit' && $order->hasPendingChanges())
        <div class="alert alert-warning border-0 shadow-sm">
            {{ __('يوجد تعديل معلّق على هذا الطلب بانتظار اعتماد المدير، لذلك تم قفل إرسال أي تعديل إضافي مؤقتًا.') }}
        </div>
    @endif

    @if ($mode === 'edit' && !$canEdit && !$order->hasPendingChanges())
        <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="fw-semibold mb-1">{{ __('تم قفل الحقول الأصلية لهذا الطلب') }}</div>
                <div>{{ __('بمجرد إرسال الطلب إلى المصنع لا يمكن تعديل البيانات الأصلية مباشرة، ويجب استخدام مسار طلب تعديل رسمي.') }}</div>
            </div>
            @if ($canRequestAdjustment)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salesAdjustmentModal">{{ __('طلب تعديل') }}</button>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('sales.orders.store') : route('sales.orders.update', $order) }}" enctype="multipart/form-data" class="row g-4">
        @csrf
        @if ($mode === 'edit')
            @method('PUT')
        @endif

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('بيانات العميل') }}</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('اسم العميل الكامل') }}</label>
                            <input type="text" name="customer_full_name" class="form-control" value="{{ old('customer_full_name', $order->customer?->full_name) }}" @readonly(!$canEdit) required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('رقم التواصل') }}</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $order->customer?->phone) }}" @readonly(!$canEdit) required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">{{ __('العنوان') }}</label>
                            <input type="text" name="customer_address" class="form-control" value="{{ old('customer_address', $order->customer?->address) }}" @readonly(!$canEdit) required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('البريد الإلكتروني') }}</label>
                            <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $order->customer?->email) }}" @readonly(!$canEdit)>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 section-title mb-0">{{ __('عناصر الطلب') }}</h2>
                        @if ($canEdit)
                            <button type="button" class="btn btn-outline-primary btn-sm" id="add-item-row">{{ __('إضافة صف') }}</button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle" id="order-items-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px;">{{ __('اسم العنصر') }}</th>
                                    <th style="width: 140px;">{{ __('الكمية') }}</th>
                                    <th>{{ __('الوصف') }}</th>
                                    <th style="width: 90px;">{{ __('إجراء') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lineItems as $index => $item)
                                    <tr class="item-row">
                                        <td>
                                            <input type="text" name="items[{{ $index }}][item_name]" class="form-control" value="{{ $item['item_name'] ?? '' }}" @readonly(!$canEdit) required>
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $item['quantity'] ?? 1 }}" @readonly(!$canEdit) required>
                                        </td>
                                        <td>
                                            <textarea name="items[{{ $index }}][description]" rows="2" class="form-control" @readonly(!$canEdit)>{{ $item['description'] ?? '' }}</textarea>
                                        </td>
                                        <td>
                                            @if ($canEdit)
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item-row">{{ __('حذف') }}</button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">{{ __('ابدأ من صف واحد وأضف ما تحتاجه من العناصر بدون إعادة تحميل الصفحة.') }}</div>
                    <div class="mt-3">
                        <label class="form-label">{{ __('ملاحظات الطلب') }}</label>
                        <textarea name="customer_notes" rows="3" class="form-control" @readonly(!$canEdit)>{{ old('customer_notes', $order->customer_notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">{{ __('المرفقات المتعددة') }}</h2>
                    <input type="file" name="attachments[]" class="form-control" multiple @readonly(!$canEdit) accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <div class="form-text">{{ __('يدعم PDF والصور وملفات المستندات المرجعية حتى 10MB لكل ملف.') }}</div>

                    @if ($mode === 'edit' && $order->attachments->count())
                        <div class="attachment-list mt-3">
                            <div class="fw-semibold mb-2">{{ __('المرفقات الحالية') }}</div>
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
                <button type="submit" class="btn btn-primary btn-lg">{{ $mode === 'create' ? __('إنشاء الطلب') : __('حفظ التعديلات') }}</button>
            </div>
        @endif
    </form>

    @push('scripts')
        <script>
            const bindItemsTable = (options) => {
                const addRowButton = document.getElementById(options.buttonId);
                const itemsTableBody = document.querySelector(`#${options.tableId} tbody`);

                if (!itemsTableBody) {
                    return;
                }

                const refreshRowIndexes = () => {
                    Array.from(itemsTableBody.querySelectorAll('.item-row')).forEach((row, index) => {
                        const itemName = row.querySelector('[data-field="item_name"]') || row.querySelector('input[name*="[item_name]"]');
                        const quantity = row.querySelector('[data-field="quantity"]') || row.querySelector('input[name*="[quantity]"]');
                        const description = row.querySelector('[data-field="description"]') || row.querySelector('textarea[name*="[description]"]');

                        if (itemName) {
                            itemName.name = `items[${index}][item_name]`;
                        }
                        if (quantity) {
                            quantity.name = `items[${index}][quantity]`;
                        }
                        if (description) {
                            description.name = `items[${index}][description]`;
                        }
                    });
                };

                if (addRowButton) {
                    addRowButton.addEventListener('click', () => {
                        const row = document.createElement('tr');
                        row.className = 'item-row';
                        row.innerHTML = `
                            <td><input type="text" data-field="item_name" class="form-control" required></td>
                            <td><input type="number" min="1" data-field="quantity" class="form-control" value="1" required></td>
                            <td><textarea data-field="description" rows="2" class="form-control"></textarea></td>
                            <td><button type="button" class="btn btn-outline-danger btn-sm remove-item-row">{{ __('حذف') }}</button></td>
                        `;
                        itemsTableBody.appendChild(row);
                        refreshRowIndexes();
                    });
                }

                itemsTableBody.addEventListener('click', (event) => {
                    const trigger = event.target.closest('.remove-item-row');
                    if (!trigger) {
                        return;
                    }

                    if (itemsTableBody.querySelectorAll('.item-row').length === 1) {
                        return;
                    }

                    trigger.closest('.item-row').remove();
                    refreshRowIndexes();
                });

                refreshRowIndexes();
            };

            bindItemsTable({ buttonId: 'add-item-row', tableId: 'order-items-table' });
            bindItemsTable({ buttonId: 'adjustment-add-item-row', tableId: 'adjustment-items-table' });

            @if ($mode === 'edit' && $canRequestAdjustment && $shouldOpenAdjustmentModal)
                const salesAdjustmentModalElement = document.getElementById('salesAdjustmentModal');
                if (salesAdjustmentModalElement) {
                    bootstrap.Modal.getOrCreateInstance(salesAdjustmentModalElement).show();
                }
            @endif
        </script>
    @endpush

    @if ($mode === 'edit' && $order->status === 'draft')
        <form method="POST" action="{{ route('sales.orders.submit', $order) }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-outline-dark btn-lg w-100">{{ __('إرسال إلى المصنع للتسعير') }}</button>
        </form>
    @endif

    @if ($mode === 'edit')
        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="card form-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">{{ __('الوثائق') }}</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($order->resolvedItems()->isNotEmpty() && $documentsEnabled)
                                <form method="POST" action="{{ route('sales.orders.quotation.generate', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success">{{ __('توليد عرض سعر PDF') }}</button>
                                </form>
                            @elseif ($order->resolvedItems()->isNotEmpty())
                                <button type="button" class="btn btn-outline-success" disabled>{{ __('توليد عرض سعر PDF') }}</button>
                            @endif
                            @if ($order->quotation_path)
                                <a href="{{ route('sales.orders.quotation.download', $order) }}" class="btn btn-success">{{ __('تحميل عرض السعر PDF') }}</a>
                            @endif
                            @if ($order->resolvedItems()->isNotEmpty() && $documentsEnabled)
                                <form method="POST" action="{{ route('sales.orders.invoice.generate', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">{{ __('توليد فاتورة PDF') }}</button>
                                </form>
                            @elseif ($order->resolvedItems()->isNotEmpty())
                                <button type="button" class="btn btn-outline-danger" disabled>{{ __('توليد فاتورة PDF') }}</button>
                            @endif
                            @if ($order->invoice_path)
                                <a href="{{ route('sales.orders.invoice.download', $order) }}" class="btn btn-danger">{{ __('تحميل الفاتورة PDF') }}</a>
                            @endif
                        </div>
                        @if (!$documentsEnabled)
                            <div class="alert alert-warning mt-3 mb-0">{{ __('سيتم تفعيل توليد عرض السعر والفاتورة فقط بعد اعتماد المدير للطلب واستكمال مسار الموافقة.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card form-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 section-title">{{ __('الإجراءات') }}</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($canRequestAdjustment)
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#salesAdjustmentModal">{{ __('طلب تعديل') }}</button>
                            @endif
                            @if ($order->status === 'approved' && !$order->customer_approval)
                                <form method="POST" action="{{ route('sales.orders.customer-approval', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-info">{{ __('تسجيل موافقة العميل') }}</button>
                                </form>
                            @endif
                            @if ($order->status === 'customer_approved' && !$order->payment_confirmed)
                                <form method="POST" action="{{ route('sales.orders.confirm-payment', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning">{{ __('تأكيد دفع القيمة') }}</button>
                                </form>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'factory_pricing')
                                <div class="alert alert-warning mb-0 w-100">{{ __('أعاد المدير الطلب لتعديل تشغيلي إضافي، وما زالت الحقول الأصلية مقفلة ويجب استخدام طلب تعديل رسمي.') }}</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'sent_to_factory')
                                <div class="alert alert-warning mb-0 w-100">{{ __('تم إرسال الطلب إلى المصنع، وأصبحت الحقول التجارية الأصلية مقفلة حتى انتهاء المعالجة.') }}</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'manager_review')
                                <div class="alert alert-info mb-0 w-100">{{ __('الطلب الآن لدى الإدارة بانتظار قرار الاعتماد.') }}</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'pending_approval')
                                <div class="alert alert-warning mb-0 w-100">{{ __('تم إرسال تعديلك للمدير وهو الآن بانتظار الاعتماد.') }}</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'completed')
                                <div class="alert alert-success mb-0 w-100">{{ __('الحالة الحالية') }}: {{ $order->status_label }}.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($mode === 'edit' && $canRequestAdjustment)
        <div class="modal fade" id="salesAdjustmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <div>
                            <h2 class="h5 mb-1">{{ __('طلب تعديل للطلب') }} {{ $order->order_number }}</h2>
                            <div class="text-muted small">{{ __('سيتم إرسال التعديلات للمدير للمراجعة والاعتماد قبل تثبيتها على السجل الأصلي.') }}</div>
                        </div>
                        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('sales.orders.adjustments.store', $order) }}" enctype="multipart/form-data" class="row g-4" id="sales-adjustment-form">
                            @csrf
                            <input type="hidden" name="submission_context" value="sales_adjustment">

                            <div class="col-12">
                                <div class="card form-card">
                                    <div class="card-body p-4">
                                        <h3 class="h5 section-title">{{ __('بيانات العميل المقترحة') }}</h3>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('اسم العميل الكامل') }}</label>
                                                <input type="text" name="customer_full_name" class="form-control" value="{{ old('customer_full_name', $order->customer?->full_name) }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('رقم التواصل') }}</label>
                                                <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $order->customer?->phone) }}" required>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">{{ __('العنوان') }}</label>
                                                <input type="text" name="customer_address" class="form-control" value="{{ old('customer_address', $order->customer?->address) }}" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">{{ __('البريد الإلكتروني') }}</label>
                                                <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $order->customer?->email) }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">{{ __('ملاحظات الطلب') }}</label>
                                                <textarea name="customer_notes" rows="3" class="form-control">{{ old('customer_notes', $order->customer_notes) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card form-card">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                            <h3 class="h5 section-title mb-0">{{ __('العناصر المقترحة') }}</h3>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="adjustment-add-item-row">{{ __('إضافة صف') }}</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table align-middle" id="adjustment-items-table">
                                                <thead>
                                                    <tr>
                                                        <th style="min-width: 220px;">{{ __('اسم العنصر') }}</th>
                                                        <th style="width: 140px;">{{ __('الكمية') }}</th>
                                                        <th>{{ __('الوصف') }}</th>
                                                        <th style="width: 90px;">{{ __('إجراء') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($lineItems as $index => $item)
                                                        <tr class="item-row">
                                                            <td>
                                                                <input type="text" name="items[{{ $index }}][item_name]" class="form-control" value="{{ $item['item_name'] ?? '' }}" required>
                                                            </td>
                                                            <td>
                                                                <input type="number" min="1" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $item['quantity'] ?? 1 }}" required>
                                                            </td>
                                                            <td>
                                                                <textarea name="items[{{ $index }}][description]" rows="2" class="form-control">{{ $item['description'] ?? '' }}</textarea>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item-row">{{ __('حذف') }}</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card form-card h-100">
                                    <div class="card-body p-4">
                                        <h3 class="h5 section-title">{{ __('مرفقات إضافية مع طلب التعديل') }}</h3>
                                        <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                        <div class="form-text">{{ __('يمكنك إرفاق ملفات داعمة أو صور أو نسخة محدثة من المرجع.') }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card form-card h-100">
                                    <div class="card-body p-4">
                                        <h3 class="h5 section-title">{{ __('المرفقات الحالية') }}</h3>
                                        <div class="attachment-list list-group list-group-flush">
                                            @forelse ($order->attachments->where('type', 'sales_upload') as $attachment)
                                                <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                    <span>{{ $attachment->original_name }}</span>
                                                    <span class="text-muted small">{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</span>
                                                </a>
                                            @empty
                                                <div class="text-muted">{{ __('لا توجد مرفقات حالية.') }}</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                        <button type="submit" form="sales-adjustment-form" class="btn btn-primary">{{ __('إرسال طلب التعديل للاعتماد') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
