@extends('layouts.app')

@section('title', 'مراجعة التعديلات المعلقة | WorkFlow')

@php
    $current = $pendingChanges['current'] ?? [];
    $proposed = $pendingChanges['proposed'] ?? [];
    $pendingRequester = is_array($pendingChanges['requested_by'] ?? null) ? $pendingChanges['requested_by'] : [];
    $requester = $order->pendingAdjustmentLog?->requester ?: $order->pendingChangeRequester;
    $requestSubmittedAt = $order->pending_change_requested_at ?: $order->pendingAdjustmentLog?->created_at;
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
    $proposedAttachments = is_array($proposed['attachments'] ?? null) ? $proposed['attachments'] : [];
    $formatMoney = function ($value) use ($priceComparison) {
        return $value === null
            ? 'بانتظار إعادة التسعير'
            : ($priceComparison['currency'] ?? 'USD') . ' ' . number_format((float) $value, 2);
    };
    $deltaLabel = $priceComparison['delta'] === null
        ? 'بانتظار إعادة التسعير'
        : ((float) $priceComparison['delta'] === 0.0
            ? 'لا يوجد تغير في السعر النهائي'
            : ((float) $priceComparison['delta'] > 0
                ? 'زيادة ' . ($priceComparison['currency'] ?? 'USD') . ' ' . number_format((float) $priceComparison['delta'], 2)
                : 'انخفاض ' . ($priceComparison['currency'] ?? 'USD') . ' ' . number_format(abs((float) $priceComparison['delta']), 2)));
@endphp

@push('styles')
    <style>
        .review-metric-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 12px 34px rgba(15, 23, 42, .06);
        }

        .review-metric-label {
            color: #64748b;
            font-size: .82rem;
            margin-bottom: .4rem;
        }

        .review-metric-value {
            font-weight: 800;
            color: #0f2f6f;
        }

        .review-table thead th {
            background: #f8fafc;
            color: #0f2f6f;
            font-weight: 700;
        }

        .review-table tbody td {
            vertical-align: middle;
        }

        .review-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: .8rem;
            font-weight: 700;
        }

        .review-pill.blue {
            background: #e7eef9;
            color: #113f87;
        }

        .review-pill.gold {
            background: #f8ecd0;
            color: #7a5600;
        }

        .review-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            padding: 18px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">مراجعة تعديل معلّق للطلب {{ $order->order_number }}</h1>
            <div class="text-muted">عرض إداري نظيف للفروقات الفعلية، مع مقارنة السعر النهائي فقط دون أي تكلفة مصنع أو بيانات خام.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card review-metric-card h-100">
                <div class="card-body">
                    <div class="review-metric-label">مقدم الطلب</div>
                    <div class="review-metric-value">{{ $requester?->name ?: ($pendingRequester['name'] ?? '—') }}</div>
                    <div class="text-muted small mt-2">{{ $roleLabels[$requester?->role ?: ($pendingRequester['role'] ?? '')] ?? '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card review-metric-card h-100">
                <div class="card-body">
                    <div class="review-metric-label">وقت الطلب</div>
                    <div class="review-metric-value">{{ $requestSubmittedAt?->format('Y-m-d H:i:s') ?: '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card review-metric-card h-100">
                <div class="card-body">
                    <div class="review-metric-label">الحالة قبل الطلب</div>
                    <div class="review-metric-value">{{ $statusLabels[$pendingChanges['previous_status'] ?? ''] ?? '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card review-metric-card h-100">
                <div class="card-body">
                    <div class="review-metric-label">الحالة بعد الاعتماد</div>
                    <div class="review-metric-value">{{ $statusLabels[$pendingChanges['target_status'] ?? ''] ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card form-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h2 class="h5 section-title mb-1">الفروقات المعروضة للإدارة</h2>
                    <div class="text-muted small">جميع السطور التالية معروضة بصيغة بشرية واضحة، بدون حقول داخلية أو JSON.</div>
                </div>
                <span class="review-pill blue">{{ count($comparisonRows) }} تغيير واضح</span>
            </div>

            @if ($comparisonRows !== [])
                <div class="table-responsive">
                    <table class="table review-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:24%;">الحقل</th>
                                <th>القيمة الحالية</th>
                                <th>القيمة المقترحة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparisonRows as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['label'] }}</td>
                                    <td>{{ $row['current'] }}</td>
                                    <td>{{ $row['proposed'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="review-empty">لا توجد فروقات نصية مباشرة، وقد يكون التغيير متعلقًا بالعناصر أو بالمرفقات فقط.</div>
            @endif
        </div>
    </div>

    <div class="card form-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h2 class="h5 section-title mb-1">مقارنة السعر النهائي</h2>
                    <div class="text-muted small">{{ $priceComparison['note'] }}</div>
                </div>
                <span class="review-pill {{ $priceComparison['requires_repricing'] ? 'gold' : 'blue' }}">{{ $deltaLabel }}</span>
            </div>

            <div class="table-responsive">
                <table class="table review-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:24%;">المعيار</th>
                            <th>الحالي</th>
                            <th>المقترح</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">إجمالي السعر النهائي</td>
                            <td>{{ $formatMoney($priceComparison['current_total']) }}</td>
                            <td>{{ $formatMoney($priceComparison['proposed_total']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">سعر الوحدة النهائي</td>
                            <td>{{ $formatMoney($priceComparison['current_unit']) }}</td>
                            <td>{{ $formatMoney($priceComparison['proposed_unit']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">الكمية</td>
                            <td>{{ number_format((int) $priceComparison['current_quantity']) }}</td>
                            <td>{{ number_format((int) $priceComparison['proposed_quantity']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">مدة الإنتاج</td>
                            <td>{{ $priceComparison['current_production_days'] }}</td>
                            <td>{{ $priceComparison['proposed_production_days'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card form-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">العناصر الحالية</h2>
                    @if (($priceComparison['current_items'] ?? []) !== [])
                        <div class="table-responsive">
                            <table class="table review-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>العنصر</th>
                                        <th>الكمية</th>
                                        <th>الوصف</th>
                                        <th>سعر نهائي للوحدة</th>
                                        <th>الإجمالي النهائي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($priceComparison['current_items'] as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item['item_name'] }}</td>
                                            <td>{{ number_format((int) $item['quantity']) }}</td>
                                            <td>{{ $item['description'] ?: '—' }}</td>
                                            <td>{{ $formatMoney($item['final_unit_price']) }}</td>
                                            <td>{{ $formatMoney($item['final_line_total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="review-empty">لا توجد عناصر حالية قابلة للعرض.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card form-card h-100 border border-primary-subtle">
                <div class="card-body p-4">
                    <h2 class="h5 section-title text-primary">العناصر المقترحة</h2>
                    @if (($priceComparison['proposed_items'] ?? []) !== [])
                        <div class="table-responsive">
                            <table class="table review-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>العنصر</th>
                                        <th>الكمية</th>
                                        <th>الوصف</th>
                                        <th>سعر نهائي للوحدة</th>
                                        <th>الإجمالي النهائي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($priceComparison['proposed_items'] as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item['item_name'] }}</td>
                                            <td>{{ number_format((int) $item['quantity']) }}</td>
                                            <td>{{ $item['description'] ?: '—' }}</td>
                                            <td>{{ $formatMoney($item['final_unit_price']) }}</td>
                                            <td>{{ $formatMoney($item['final_line_total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="review-empty">لا توجد عناصر مقترحة قابلة للعرض.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($proposedAttachments !== [])
        <div class="card form-card mb-4">
            <div class="card-body p-4">
                <h2 class="h5 section-title">المرفقات الجديدة بانتظار التثبيت</h2>
                <div class="list-group list-group-flush">
                    @foreach ($proposedAttachments as $attachment)
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>{{ $attachment['original_name'] ?? 'attachment' }}</span>
                            <span class="text-muted small">{{ strtoupper(pathinfo($attachment['original_name'] ?? '', PATHINFO_EXTENSION)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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
