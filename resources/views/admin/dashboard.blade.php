@extends('layouts.app')

@section('title', 'لوحة الاعتماد | WorkFlow')

@push('styles')
    <style>
        .dashboard-hero {
            background: linear-gradient(135deg, rgba(17, 63, 135, .96), rgba(15, 47, 111, .94));
            color: #fff;
            border-radius: 22px;
            padding: 24px 26px;
            box-shadow: 0 18px 44px rgba(15, 47, 111, .18);
        }

        .dashboard-hero .subline {
            color: rgba(255, 255, 255, .8);
        }

        .brand-stat-card {
            border: 1px solid rgba(17, 63, 135, .08);
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, .08);
        }

        .brand-stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .brand-stat-blue {
            color: #113f87;
        }

        .brand-stat-gold {
            color: #c49b2d;
        }

        .brand-filter {
            min-width: 240px;
        }

        .group-card {
            border: 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .08);
        }

        .group-card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .group-card-header.brand-blue {
            background: linear-gradient(135deg, rgba(17, 63, 135, .12), rgba(17, 63, 135, .04));
        }

        .group-card-header.brand-gold {
            background: linear-gradient(135deg, rgba(196, 155, 45, .18), rgba(196, 155, 45, .08));
        }

        .group-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 42px;
            padding: 0 12px;
            border-radius: 999px;
            font-weight: 800;
            color: #fff;
            background: #113f87;
        }

        .group-badge.gold {
            background: #c49b2d;
            color: #3f2b00;
        }

        .table-brand thead th {
            background: #f8fafc;
            color: #0f2f6f;
            font-weight: 700;
        }

        .table-brand tbody td {
            vertical-align: middle;
        }

        .owner-chip {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            background: #eef3fb;
            color: #113f87;
            font-size: .82rem;
            font-weight: 700;
        }

        .owner-chip.gold {
            background: #f8ecd0;
            color: #7a5600;
        }
    </style>
@endpush

@section('content')
    @php
        $groupMeta = [
            'new_pending' => [
                'title' => 'طلبات جديدة / معلقة',
                'description' => 'طلبات تحتاج اعتمادًا أو مراجعة تعديل أو لم تُحسم بعد.',
                'header_class' => 'brand-gold',
                'badge_class' => 'gold',
            ],
            'in_progress' => [
                'title' => 'طلبات جاري العمل عليها',
                'description' => 'طلبات في مسار التشغيل والتسعير داخل المصنع.',
                'header_class' => 'brand-blue',
                'badge_class' => '',
            ],
            'approved' => [
                'title' => 'طلبات تم اعتمادها نهائيًا',
                'description' => 'طلبات جاهزة تجاريًا أو وصلت للمراحل النهائية.',
                'header_class' => 'brand-blue',
                'badge_class' => '',
            ],
        ];
    @endphp

    <div class="dashboard-hero mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-2 text-white">لوحة الاعتماد والإشراف</h1>
                <div class="subline">عرض مرتب للطلبات حسب المرحلة: جديدة/معلقة، جاري العمل عليها، ومعتمدة نهائيًا، مع منع أي بيانات خام أو رموز مشوهة داخل الجداول.</div>
            </div>
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <select name="group" class="form-select brand-filter">
                    <option value="">كل المجموعات</option>
                    <option value="new_pending" @selected(($filters['group'] ?? '') === 'new_pending')>طلبات جديدة / معلقة</option>
                    <option value="in_progress" @selected(($filters['group'] ?? '') === 'in_progress')>طلبات جاري العمل عليها</option>
                    <option value="approved" @selected(($filters['group'] ?? '') === 'approved')>طلبات معتمدة نهائيًا</option>
                </select>
                <button type="submit" class="btn btn-light">تصفية</button>
                @if (($filters['group'] ?? '') !== '')
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-light">إلغاء</a>
                @endif
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card brand-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">إجمالي الطلبات</div>
                    <div class="brand-stat-value brand-stat-blue">{{ $stats['total_orders'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card brand-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">طلبات جديدة / معلقة</div>
                    <div class="brand-stat-value brand-stat-gold">{{ $stats['new_pending'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card brand-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">جاري العمل عليها</div>
                    <div class="brand-stat-value brand-stat-blue">{{ $stats['in_progress'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card brand-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">تعديلات بانتظار الاعتماد</div>
                    <div class="brand-stat-value brand-stat-gold">{{ $stats['pending_adjustments'] }}</div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($groupedOrders as $groupKey => $orders)
        @php
            $meta = $groupMeta[$groupKey] ?? $groupMeta['new_pending'];
        @endphp
        <div class="group-card mb-4">
            <div class="group-card-header {{ $meta['header_class'] }}">
                <div>
                    <h2 class="h5 mb-1">{{ $meta['title'] }}</h2>
                    <div class="text-muted small">{{ $meta['description'] }}</div>
                </div>
                <span class="group-badge {{ $meta['badge_class'] }}">{{ $orders->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-brand align-middle mb-0">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>المنتج</th>
                                <th>الحالة</th>
                                <th>المسؤول الحالي</th>
                                <th>آخر تحديث</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                @php
                                    $hasPendingChange = $order->hasPendingChanges();
                                    $currentOwner = $hasPendingChange
                                        ? ($order->pendingAdjustmentLog?->requester?->name ?: $order->pendingChangeRequester?->name)
                                        : (in_array($order->status, ['sent_to_factory', 'factory_pricing', 'manager_review'], true)
                                            ? optional($order->factoryUser)->name
                                            : optional($order->salesUser)->name);
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $order->order_number }}</td>
                                    <td>{{ $order->resolvedCustomerName() ?: '—' }}</td>
                                    <td>{{ $order->product_name ?: '—' }}</td>
                                    <td><span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span></td>
                                    <td>
                                        <span class="owner-chip {{ in_array($order->status, ['manager_review', 'pending_approval'], true) ? 'gold' : '' }}">
                                            {{ $currentOwner ?: '—' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($order->updated_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                    <td>
                                        @if ($hasPendingChange)
                                            <a href="{{ route('admin.orders.pending-changes.review', $order) }}" class="btn btn-sm btn-primary">مراجعة التعديل</a>
                                        @elseif ($order->status === 'manager_review')
                                            <a href="{{ route('admin.orders.review', $order) }}" class="btn btn-sm btn-outline-primary">مراجعة الطلب</a>
                                        @else
                                            <span class="text-muted">لا إجراء مطلوب</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">لا توجد طلبات ضمن هذه المجموعة حاليًا.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <div class="card page-card">
        <div class="card-body">
            <h2 class="h5 section-title mb-3">إرشادات قرار الإدارة</h2>
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="fw-semibold mb-2">مراجعة التعديل</div>
                        <div class="text-muted small">تُعرض المقارنة بصيغة بشرية واضحة، مع إبراز السعر النهائي فقط عند توفره، دون أي JSON خام أو تكلفة مصنع داخل الصفحة.</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="fw-semibold mb-2">جاري العمل عليها</div>
                        <div class="text-muted small">طلبات ما زالت في المصنع أو في مرحلة التسعير والتجهيز التشغيلي قبل الإغلاق التجاري.</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="fw-semibold mb-2">اعتماد نهائي</div>
                        <div class="text-muted small">طلبات أصبحت جاهزة تجاريًا أو تم تأكيدها في المراحل النهائية بعد اكتمال المسار الإداري.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
