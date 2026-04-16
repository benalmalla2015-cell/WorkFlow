@extends('layouts.app')

@section('title', 'طلب تعديل | WorkFlow')

@php
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
            <h1 class="h3 mb-1">طلب تعديل للطلب {{ $order->order_number }}</h1>
            <div class="text-muted">سيتم إرسال التعديلات للمدير للمراجعة والاعتماد قبل تثبيتها على السجل الأصلي.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm">
        الحقول الأصلية مقفلة لأن الطلب دخل المسار التشغيلي. عبّئ النموذج التالي وسيبقى الطلب الأصلي دون تعديل حتى اعتماد الإدارة.
    </div>

    <form method="POST" action="{{ route('sales.orders.adjustments.store', $order) }}" enctype="multipart/form-data" class="row g-4">
        @csrf

        <div class="col-12">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">بيانات العميل المقترحة</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">اسم العميل الكامل</label>
                            <input type="text" name="customer_full_name" class="form-control" value="{{ old('customer_full_name', $order->customer?->full_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم التواصل</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $order->customer?->phone) }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="customer_address" class="form-control" value="{{ old('customer_address', $order->customer?->address) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $order->customer?->email) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">ملاحظات الطلب</label>
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
                        <h2 class="h5 section-title mb-0">العناصر المقترحة</h2>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-item-row">إضافة صف</button>
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
                                            <input type="text" name="items[{{ $index }}][item_name]" class="form-control" value="{{ $item['item_name'] ?? '' }}" required>
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $item['quantity'] ?? 1 }}" required>
                                        </td>
                                        <td>
                                            <textarea name="items[{{ $index }}][description]" rows="2" class="form-control">{{ $item['description'] ?? '' }}</textarea>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-item-row">حذف</button>
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
                    <h2 class="h5 section-title">مرفقات إضافية مع طلب التعديل</h2>
                    <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <div class="form-text">يمكنك إرفاق ملفات داعمة أو صور أو نسخة محدثة من المرجع.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card form-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">المرفقات الحالية</h2>
                    <div class="attachment-list list-group list-group-flush">
                        @forelse ($order->attachments->where('type', 'sales_upload') as $attachment)
                            <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>{{ $attachment->original_name }}</span>
                                <span class="text-muted small">{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</span>
                            </a>
                        @empty
                            <div class="text-muted">لا توجد مرفقات حالية.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg">إرسال طلب التعديل للاعتماد</button>
        </div>
    </form>
@endsection

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
