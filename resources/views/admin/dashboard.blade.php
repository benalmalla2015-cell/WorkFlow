@extends('layouts.app')

@section('title', 'لوحة الاعتماد | WorkFlow')

@section('content')
    @php
        $changeFieldLabels = [
            'customer.full_name' => 'اسم العميل',
            'customer.address' => 'عنوان العميل',
            'customer.phone' => 'رقم التواصل',
            'customer.email' => 'البريد الإلكتروني',
            'order.customer_name' => 'اسم العميل على الطلب',
            'order.product_name' => 'اسم المنتج',
            'order.quantity' => 'الكمية',
            'order.specifications' => 'المواصفات',
            'order.customer_notes' => 'ملاحظات الطلب',
            'order.supplier_name' => 'اسم المورد',
            'order.product_code' => 'كود المنتج',
            'order.factory_cost' => 'تكلفة المصنع',
            'order.selling_price' => 'سعر البيع',
            'order.profit_margin_percentage' => 'هامش الربح',
            'order.final_price' => 'السعر النهائي',
            'order.production_days' => 'مدة الإنتاج',
            'order.factory_user_id' => 'مسؤول المصنع',
            'order.status' => 'الحالة',
        ];
        $roleLabels = [
            'admin' => 'الإدارة',
            'sales' => 'المبيعات',
            'factory' => 'المصنع',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">لوحة الاعتماد</h1>
            <div class="text-muted">عرض مركزي للطلبات التي تحتاج قرار اعتماد أو مراجعة تعديل قبل الانتقال للمرحلة التالية.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select">
                <option value="">كل ما ينتظر الاعتماد</option>
                <option value="manager_review" @selected(($filters['status'] ?? '') === 'manager_review')>طلبات بانتظار المدير</option>
                <option value="pending_approval" @selected(($filters['status'] ?? '') === 'pending_approval')>طلبات تعديل معلقة</option>
                <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>طلبات معتمدة</option>
                <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>طلبات مكتملة</option>
            </select>
            <button type="submit" class="btn btn-dark">تصفية</button>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">إجمالي الطلبات</div>
                    <div class="display-6 fw-bold">{{ $stats['total_orders'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">بانتظار الإجراء</div>
                    <div class="display-6 fw-bold text-warning">{{ $stats['pending_orders'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">طلبات مراجعة المدير</div>
                    <div class="display-6 fw-bold text-primary">{{ $stats['pending_manager_reviews'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">طلبات التعديل المعلقة</div>
                    <div class="display-6 fw-bold text-danger">{{ $stats['pending_adjustments'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 section-title">طلبات بانتظار اعتماد المدير</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المنتج</th>
                            <th>تكلفة المصنع</th>
                            <th>الحالة الحالية</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingApprovals as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->resolvedCustomerName() ?: '—' }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td>{{ $order->factory_cost ? '$' . number_format((float) $order->factory_cost, 2) : '—' }}</td>
                                <td><span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span></td>
                                <td>
                                    <a href="{{ route('admin.orders.review', $order) }}" class="btn btn-sm btn-primary">مراجعة واعتماد</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">لا توجد طلبات لدى المدير حاليًا.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 section-title">طلبات تعديل بانتظار الاعتماد</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>مقدم الطلب</th>
                            <th>الدور</th>
                            <th>وقت الإرسال</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingChangeRequests as $order)
                            @php
                                $requester = $order->pendingAdjustmentLog?->requester ?: $order->pendingChangeRequester;
                                $pendingRequester = is_array(data_get($order->pending_changes, 'requested_by'))
                                    ? data_get($order->pending_changes, 'requested_by')
                                    : [];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $requester?->name ?: ($pendingRequester['name'] ?? '—') }}</td>
                                <td>{{ $roleLabels[$requester?->role ?: ($pendingRequester['role'] ?? '')] ?? '—' }}</td>
                                <td>{{ $order->pending_change_requested_at?->format('Y-m-d H:i') ?: '—' }}</td>
                                <td>
                                    <a href="{{ route('admin.orders.pending-changes.review', $order) }}" class="btn btn-sm btn-primary">مقارنة واعتماد</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">لا توجد تعديلات معلقة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 section-title">إرشادات قرار الإدارة</h2>
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="fw-semibold mb-2">اعتماد</div>
                        <div class="text-muted small">اعتمد الطلب عندما تكون تكلفة المصنع والمرفقات والهوامش مكتملة وجاهزة للإرسال التجاري.</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="fw-semibold mb-2">طلب تعديل</div>
                        <div class="text-muted small">استخدمه إذا احتجت تصحيحًا تشغيليًا أو تجاريًا قبل الاعتماد النهائي، مع كتابة سبب واضح يظهر في الإشعار.</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="fw-semibold mb-2">رفض</div>
                        <div class="text-muted small">ارفض الطلب أو التعديل إذا كان غير مطابق، وسيعود للمسار السابق مع توثيق سبب القرار.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المنتج</th>
                            <th>الحالة</th>
                            <th>صاحب المهمة الحالية</th>
                            <th>الإجراء السريع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $hasPendingChange = $order->hasPendingChanges();
                                $currentOwner = $hasPendingChange
                                    ? ($order->pendingAdjustmentLog?->requester?->name ?: $order->pendingChangeRequester?->name)
                                    : ($order->status === 'manager_review'
                                        ? optional($order->factoryUser)->name
                                        : optional($order->salesUser)->name);
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->resolvedCustomerName() ?: '—' }}</td>
                                <td>{{ $order->product_name }}</td>
                                <td><span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span></td>
                                <td>{{ $currentOwner ?: '—' }}</td>
                                <td>
                                    @if ($hasPendingChange)
                                        <a href="{{ route('admin.orders.pending-changes.review', $order) }}" class="btn btn-sm btn-outline-danger">مراجعة التعديل</a>
                                    @elseif ($order->status === 'manager_review')
                                        <a href="{{ route('admin.orders.review', $order) }}" class="btn btn-sm btn-outline-primary">مراجعة الطلب</a>
                                    @else
                                        <span class="text-muted">لا إجراء</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">لا توجد طلبات مطابقة للتصفية الحالية.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endsection
