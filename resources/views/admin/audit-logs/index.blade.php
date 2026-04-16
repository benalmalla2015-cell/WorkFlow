@extends('layouts.app')

@section('title', 'السجلات التدقيقية | WorkFlow')

@section('content')
    @php
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
            'order.profit_margin_percentage' => 'هامش الربح',
            'order.final_price' => 'السعر النهائي',
            'order.factory_user_id' => 'مسؤول المصنع',
            'order.status' => 'الحالة',
        ];
    @endphp

    <div class="mb-4">
        <h1 class="h3 mb-1">السجلات التدقيقية</h1>
        <div class="text-muted">تتبع العمليات الحساسة في النظام لحظيًا.</div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">الإجراء</label>
                    <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" class="form-control" placeholder="login, order_created ...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المستخدم</label>
                    <select name="user_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark flex-fill">تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الوقت</th>
                            <th>المستخدم</th>
                            <th>الإجراء</th>
                            <th>النموذج</th>
                            <th>المعرف</th>
                            <th>الحقول المتغيرة</th>
                            <th>IP</th>
                            <th>تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $changedFields = collect($log->changed_fields ?? [])
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
                            <tr>
                                <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                <td>{{ optional($log->user)->name ?: '—' }}</td>
                                <td><span class="badge text-bg-dark">{{ $log->action }}</span></td>
                                <td>{{ $log->model_type ? class_basename($log->model_type) : '—' }}</td>
                                <td>{{ $log->model_id ?: '—' }}</td>
                                <td>
                                    @if ($changedFields->isNotEmpty())
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($changedFields as $field)
                                                <span class="badge text-bg-light border">{{ $field }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $log->ip_address ?: '—' }}</td>
                                <td>
                                    <details>
                                        <summary class="small text-primary" style="cursor: pointer;">عرض السجل</summary>
                                        <div class="mt-2 small">
                                            <div class="fw-semibold mb-1">القيم السابقة</div>
                                            @if ($log->old_values)
                                                <pre class="small mb-2" style="white-space: pre-wrap;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @else
                                                <div class="text-muted mb-2">—</div>
                                            @endif
                                            <div class="fw-semibold mb-1">القيم الجديدة</div>
                                            @if ($log->new_values)
                                                <pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @else
                                                <div class="text-muted">—</div>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">لا توجد سجلات.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
