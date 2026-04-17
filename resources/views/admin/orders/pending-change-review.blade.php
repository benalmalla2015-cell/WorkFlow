@extends('layouts.app')

@section('title', 'مراجعة التعديلات المعلقة | WorkFlow')

@php
    $current = $pendingChanges['current'] ?? [];
    $proposed = $pendingChanges['proposed'] ?? [];
    $rawChangedFields = $pendingChanges['changed_fields'] ?? [];
    $pendingRequester = is_array($pendingChanges['requested_by'] ?? null) ? $pendingChanges['requested_by'] : [];
    $requester = $order->pendingAdjustmentLog?->requester ?: $order->pendingChangeRequester;
    $roleLabels = [
        'admin' => 'الإدارة',
        'sales' => 'المبيعات',
        'factory' => 'المصنع',
    ];
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
    $customerLabels = [
        'full_name' => 'اسم العميل',
        'address' => 'العنوان',
        'phone' => 'رقم التواصل',
        'email' => 'البريد الإلكتروني',
    ];
    $orderLabels = [
        'customer_name' => 'العميل على الطلب',
        'product_name' => 'المنتج',
        'quantity' => 'الكمية',
        'specifications' => 'المواصفات',
        'customer_notes' => 'ملاحظات العميل',
        'supplier_name' => 'اسم المورد',
        'product_code' => 'كود المنتج',
        'factory_cost' => 'تكلفة المصنع',
        'production_days' => 'مدة الإنتاج',
        'selling_price' => 'سعر البيع',
        'profit_margin_percentage' => 'الهامش',
        'final_price' => 'السعر النهائي',
        'status' => 'الحالة',
        'factory_user_id' => 'معرف موظف المصنع',
    ];
    $changeFieldLabels = [
        'customer.full_name' => 'اسم العميل',
        'customer.address' => 'العنوان',
        'customer.phone' => 'رقم التواصل',
        'customer.email' => 'البريد الإلكتروني',
        'order.customer_name' => 'العميل على الطلب',
        'order.product_name' => 'المنتج',
        'order.quantity' => 'الكمية',
        'order.specifications' => 'المواصفات',
        'order.customer_notes' => 'ملاحظات العميل',
        'order.supplier_name' => 'اسم المورد',
        'order.product_code' => 'كود المنتج',
        'order.factory_cost' => 'تكلفة المصنع',
        'order.production_days' => 'مدة الإنتاج',
        'order.selling_price' => 'سعر البيع',
        'order.profit_margin_percentage' => 'الهامش',
        'order.final_price' => 'السعر النهائي',
        'order.status' => 'الحالة',
        'order.factory_user_id' => 'مسؤول المصنع',
    ];
    $changedFields = collect($rawChangedFields)
        ->map(function ($field) use ($changeFieldLabels) {
            if (isset($changeFieldLabels[$field])) {
                return $changeFieldLabels[$field];
            }

            if (preg_match('/^items\.\d+\./', $field)) {
                return 'عناصر الطلب';
            }

            if (preg_match('/^attachments\.\d+\.(original_name|file_name)$/', $field)) {
                return 'المرفقات';
            }

            if (preg_match('/^attachments\.\d+\./', $field)) {
                return null;
            }

            return str($field)
                ->replace(['order.', 'customer.'], '')
                ->replace('_', ' ')
                ->title()
                ->toString();
        })
        ->filter()
        ->unique()
        ->values();
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">مراجعة تعديل معلّق للطلب {{ $order->order_number }}</h1>
            <div class="text-muted">مقارنة البيانات الحالية مع التعديلات المقترحة قبل تثبيت أي تغيير على السجل الأصلي.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-start">
                <div class="col-lg-4">
                    <div class="fw-semibold mb-1">مقدم الطلب</div>
                    <div>{{ $requester?->name ?: ($pendingRequester['name'] ?? '—') }}</div>
                    <div class="text-muted small">{{ $roleLabels[$requester?->role ?: ($pendingRequester['role'] ?? '')] ?? '—' }}</div>
                </div>
                <div class="col-lg-4">
                    <div class="fw-semibold mb-1">وقت الطلب</div>
                    <div>{{ $order->pending_change_requested_at?->format('Y-m-d H:i:s') ?: '—' }}</div>
                </div>
                <div class="col-lg-4">
                    <div class="fw-semibold mb-1">الحالة قبل الطلب</div>
                    <div>{{ $statusLabels[$pendingChanges['previous_status'] ?? ''] ?? '—' }}</div>
                </div>
                <div class="col-lg-4">
                    <div class="fw-semibold mb-1">الحالة بعد الاعتماد</div>
                    <div>{{ $statusLabels[$pendingChanges['target_status'] ?? ''] ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card form-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">البيانات الحالية</h2>

                    @if (!empty($current['customer']))
                        <div class="mb-4">
                            <div class="fw-semibold mb-2">بيانات العميل</div>
                            <dl class="row mb-0">
                                @foreach ($current['customer'] as $key => $value)
                                    <dt class="col-sm-4">{{ $customerLabels[$key] ?? $key }}</dt>
                                    <dd class="col-sm-8">{{ filled($value) ? $value : '—' }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (!empty($current['order']))
                        <div class="mb-4">
                            <div class="fw-semibold mb-2">بيانات الطلب</div>
                            <dl class="row mb-0">
                                @foreach ($current['order'] as $key => $value)
                                    <dt class="col-sm-4">{{ $orderLabels[$key] ?? $key }}</dt>
                                    <dd class="col-sm-8">{{ filled($value) ? $value : '—' }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (!empty($current['items']))
                        <div>
                            <div class="fw-semibold mb-2">العناصر الحالية</div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>العنصر</th>
                                            <th>الكمية</th>
                                            <th>الوصف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($current['items'] as $item)
                                            <tr>
                                                <td>{{ $item['item_name'] ?? '—' }}</td>
                                                <td>{{ $item['quantity'] ?? '—' }}</td>
                                                <td>{{ $item['description'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card form-card h-100 border border-primary-subtle">
                <div class="card-body p-4">
                    <h2 class="h5 section-title text-primary">التعديلات المقترحة</h2>

                    @if (!empty($proposed['customer']))
                        <div class="mb-4">
                            <div class="fw-semibold mb-2">بيانات العميل المقترحة</div>
                            <dl class="row mb-0">
                                @foreach ($proposed['customer'] as $key => $value)
                                    <dt class="col-sm-4">{{ $customerLabels[$key] ?? $key }}</dt>
                                    <dd class="col-sm-8">{{ filled($value) ? $value : '—' }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (!empty($proposed['order']))
                        <div class="mb-4">
                            <div class="fw-semibold mb-2">بيانات الطلب المقترحة</div>
                            <dl class="row mb-0">
                                @foreach ($proposed['order'] as $key => $value)
                                    <dt class="col-sm-4">{{ $orderLabels[$key] ?? $key }}</dt>
                                    <dd class="col-sm-8">{{ filled($value) ? $value : '—' }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (!empty($proposed['items']))
                        <div class="mb-4">
                            <div class="fw-semibold mb-2">العناصر المقترحة</div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>العنصر</th>
                                            <th>الكمية</th>
                                            <th>الوصف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($proposed['items'] as $item)
                                            <tr>
                                                <td>{{ $item['item_name'] ?? '—' }}</td>
                                                <td>{{ $item['quantity'] ?? '—' }}</td>
                                                <td>{{ $item['description'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if (!empty($proposed['attachments']))
                        <div>
                            <div class="fw-semibold mb-2">مرفقات جديدة بانتظار التثبيت</div>
                            <div class="list-group list-group-flush">
                                @foreach ($proposed['attachments'] as $attachment)
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span>{{ $attachment['original_name'] ?? 'attachment' }}</span>
                                        <span class="text-muted small">{{ strtoupper(pathinfo($attachment['original_name'] ?? '', PATHINFO_EXTENSION)) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('admin.orders.pending-changes.approve', $order) }}" class="card form-card h-100">
                @csrf
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="h5 section-title text-success">اعتماد التعديل</h2>
                        <div class="text-muted">سيتم تثبيت التعديلات على البيانات الأصلية، وتسجيل العملية في سجل الرقابة، ثم إشعار الموظف عبر الجرس.</div>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg mt-3">اعتماد التعديل</button>
                </div>
            </form>
        </div>

        <div class="col-lg-6">
            <form method="POST" action="{{ route('admin.orders.pending-changes.reject', $order) }}" class="card form-card h-100">
                @csrf
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="h5 section-title text-danger">رفض التعديل</h2>
                        <label class="form-label">سبب الرفض</label>
                        <textarea name="reason" rows="4" class="form-control" placeholder="أدخل سببًا اختياريًا يظهر للموظف في الإشعار..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg mt-3">رفض التعديل</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <form method="POST" action="{{ route('admin.orders.pending-changes.revision', $order) }}" class="card form-card">
                @csrf
                <div class="card-body p-4">
                    <h2 class="h5 section-title text-warning">طلب استكمال أو تعديل إضافي</h2>
                    <div class="text-muted mb-3">استخدم هذا الخيار عندما يكون طلب التعديل قريبًا من القبول لكنه يحتاج استكمالًا أو تصحيحًا إضافيًا قبل الاعتماد.</div>
                    <label class="form-label">ملاحظات المدير</label>
                    <textarea name="reason" rows="4" class="form-control" placeholder="اكتب ما الذي يجب استكماله أو تعديله قبل إعادة الإرسال..."></textarea>
                    <button type="submit" class="btn btn-outline-warning mt-3">إعادة الطلب للاستكمال</button>
                </div>
            </form>
        </div>
    </div>
@endsection
