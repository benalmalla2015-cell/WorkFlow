@extends('layouts.app')

@section('title', ($mode === 'create' ? 'طلب جديد' : 'تعديل الطلب') . ' | WorkFlow')

@php
    $canEdit = $mode === 'create' || ($mode === 'edit' && $order->canBeEditedBy(auth()->user()));
    $showOrderGuidance = !auth()->user()?->isAdmin();
    $canRequestAdjustment = $mode === 'edit'
        && !$canEdit
        && !$order->hasPendingChanges()
        && $order->canRequestAdjustmentBy(auth()->user());
    $lineItems = collect(old('items', $order->resolvedItems()->map(fn ($item) => [
        'item_name' => $item->item_name,
        'quantity' => $item->quantity,
        'description' => $item->description,
    ])->values()->all()));
    if ($lineItems->isEmpty()) {
        $lineItems = collect([['item_name' => '', 'quantity' => 1, 'description' => '']]);
    }
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $mode === 'create' ? 'إضافة طلب مبيعات' : 'الطلب ' . $order->order_number }}</h1>
            <div class="text-muted">إدخال بيانات العميل وعناصر الطلب والمرفقات وإدارة مستندات PDF الرسمية.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($mode === 'edit')
                <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            @endif
            <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    @if ($mode === 'edit' && $order->hasPendingChanges())
        <div class="alert alert-warning border-0 shadow-sm">
            يوجد تعديل معلّق على هذا الطلب بانتظار اعتماد المدير، لذلك تم قفل إرسال أي تعديل إضافي مؤقتًا.
        </div>
    @endif

    @if ($mode === 'edit' && !$canEdit && !$order->hasPendingChanges())
        <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="fw-semibold mb-1">تم قفل الحقول الأصلية لهذا الطلب</div>
                <div>بمجرد إرسال الطلب إلى المصنع لا يمكن تعديل البيانات الأصلية مباشرة، ويجب استخدام مسار طلب تعديل رسمي.</div>
            </div>
            @if ($canRequestAdjustment)
                <a href="{{ route('sales.orders.adjustments.create', $order) }}" class="btn btn-primary">طلب تعديل</a>
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
                    <h2 class="h5 section-title">بيانات العميل</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">اسم العميل الكامل</label>
                            <input type="text" name="customer_full_name" class="form-control" value="{{ old('customer_full_name', $order->customer?->full_name) }}" @readonly(!$canEdit) required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم التواصل</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $order->customer?->phone) }}" @readonly(!$canEdit) required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="customer_address" class="form-control" value="{{ old('customer_address', $order->customer?->address) }}" @readonly(!$canEdit) required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">البريد الإلكتروني</label>
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
                        <h2 class="h5 section-title mb-0">عناصر الطلب</h2>
                        @if ($canEdit)
                            <button type="button" class="btn btn-outline-primary btn-sm" id="add-item-row">إضافة صف</button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle" id="order-items-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px;">اسم العنصر</th>
                                    <th style="width: 140px;">الكمية</th>
                                    <th>الوصف</th>
                                    <th style="width: 90px;">إجراء</th>
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
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item-row">حذف</button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">ابدأ من صف واحد وأضف ما تحتاجه من العناصر بدون إعادة تحميل الصفحة.</div>
                    <div class="mt-3">
                        <label class="form-label">ملاحظات الطلب</label>
                        <textarea name="customer_notes" rows="3" class="form-control" @readonly(!$canEdit)>{{ old('customer_notes', $order->customer_notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">المرفقات المتعددة</h2>
                    <input type="file" name="attachments[]" class="form-control" multiple @readonly(!$canEdit) accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <div class="form-text">يدعم PDF والصور وملفات المستندات المرجعية حتى 10MB لكل ملف.</div>

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

    @push('scripts')
        <script>
            const addRowButton = document.getElementById('add-item-row');
            const itemsTableBody = document.querySelector('#order-items-table tbody');

            const refreshRowIndexes = () => {
                if (!itemsTableBody) {
                    return;
                }

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

            if (addRowButton && itemsTableBody) {
                addRowButton.addEventListener('click', () => {
                    const row = document.createElement('tr');
                    row.className = 'item-row';
                    row.innerHTML = `
                        <td><input type="text" data-field="item_name" class="form-control" required></td>
                        <td><input type="number" min="1" data-field="quantity" class="form-control" value="1" required></td>
                        <td><textarea data-field="description" rows="2" class="form-control"></textarea></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm remove-item-row">حذف</button></td>
                    `;
                    itemsTableBody.appendChild(row);
                    refreshRowIndexes();
                });

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
            }
        </script>
    @endpush

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
                            @if ($order->resolvedItems()->isNotEmpty())
                                <form method="POST" action="{{ route('sales.orders.quotation.generate', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success">توليد عرض سعر PDF</button>
                                </form>
                            @endif
                            @if ($order->quotation_path)
                                <a href="{{ route('sales.orders.quotation.download', $order) }}" class="btn btn-success">تحميل عرض السعر PDF</a>
                            @endif
                            @if ($order->resolvedItems()->isNotEmpty())
                                <form method="POST" action="{{ route('sales.orders.invoice.generate', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">توليد فاتورة PDF</button>
                                </form>
                            @endif
                            @if ($order->invoice_path)
                                <a href="{{ route('sales.orders.invoice.download', $order) }}" class="btn btn-danger">تحميل الفاتورة PDF</a>
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
                            @if ($canRequestAdjustment)
                                <a href="{{ route('sales.orders.adjustments.create', $order) }}" class="btn btn-outline-primary">طلب تعديل</a>
                            @endif
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
                            @if ($showOrderGuidance && $order->status === 'factory_pricing')
                                <div class="alert alert-warning mb-0 w-100">أعاد المدير الطلب لتعديل تشغيلي إضافي، وما زالت الحقول الأصلية مقفلة ويجب استخدام طلب تعديل رسمي.</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'sent_to_factory')
                                <div class="alert alert-warning mb-0 w-100">تم إرسال الطلب إلى المصنع، وأصبحت الحقول التجارية الأصلية مقفلة حتى انتهاء المعالجة.</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'manager_review')
                                <div class="alert alert-info mb-0 w-100">الطلب الآن لدى الإدارة بانتظار قرار الاعتماد.</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'pending_approval')
                                <div class="alert alert-warning mb-0 w-100">تم إرسال تعديلك للمدير وهو الآن بانتظار الاعتماد.</div>
                            @endif
                            @if ($showOrderGuidance && $order->status === 'completed')
                                <div class="alert alert-success mb-0 w-100">الحالة الحالية: {{ $order->status_label }}.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
