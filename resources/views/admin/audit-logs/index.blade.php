@extends('layouts.app')

@section('title', __('السجلات التدقيقية') . ' | WorkFlow')

@section('content')
    @php
        $changeFieldLabels = [
            'customer.full_name' => __('اسم العميل'),
            'customer.address' => __('العنوان'),
            'customer.phone' => __('رقم التواصل'),
            'customer.email' => __('البريد الإلكتروني'),
            'order.customer_name' => __('العميل على الطلب'),
            'order.product_name' => __('المنتج'),
            'order.quantity' => __('الكمية'),
            'order.specifications' => __('المواصفات'),
            'order.customer_notes' => __('ملاحظات العميل'),
            'order.supplier_name' => __('اسم المورد'),
            'order.product_code' => __('كود المنتج'),
            'order.factory_cost' => __('تكلفة المصنع'),
            'order.production_days' => __('مدة الإنتاج'),
            'order.selling_price' => __('سعر البيع'),
            'order.profit_margin_percentage' => __('هامش الربح'),
            'order.final_price' => __('السعر النهائي'),
            'order.total_price' => __('إجمالي السعر'),
            'order.net_profit' => __('صافي الربح'),
            'order.factory_user_id' => __('مسؤول المصنع'),
            'order.status' => __('الحالة'),
        ];
    @endphp

    <div class="mb-4">
        <h1 class="h3 mb-1">{{ __('السجلات التدقيقية') }}</h1>
        <div class="text-muted">{{ __('تتبع العمليات الحساسة في النظام لحظيًا.') }}</div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">{{ __('الإجراء') }}</label>
                    <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" class="form-control" placeholder="{{ __('login, order_created ...') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('المستخدم') }}</label>
                    <select name="user_id" class="form-select">
                        <option value="">{{ __('الكل') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('من تاريخ') }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('إلى تاريخ') }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark flex-fill">{{ __('تصفية') }}</button>
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
                            <th>{{ __('الوقت') }}</th>
                            <th>{{ __('المستخدم') }}</th>
                            <th>{{ __('الإجراء') }}</th>
                            <th>{{ __('النموذج') }}</th>
                            <th>{{ __('المعرف') }}</th>
                            <th>{{ __('الملخص العربي') }}</th>
                            <th>{{ __('IP') }}</th>
                            <th>{{ __('تفاصيل') }}</th>
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
                                            return __('عناصر الطلب');
                                        }

                                        if (preg_match('/^attachments\.\d+\.(original_name|file_name)$/', $field)) {
                                            return __('المرفقات');
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
                                <td><span class="badge text-bg-dark">{{ $log->humanActionLabel() }}</span></td>
                                <td>{{ $log->model_type ? class_basename($log->model_type) : '—' }}</td>
                                <td>{{ $log->model_id ?: '—' }}</td>
                                <td>
                                    <div class="small">{{ $log->humanSummary() }}</div>
                                </td>
                                <td>{{ $log->ip_address ?: '—' }}</td>
                                <td>
                                    <details>
                                        <summary class="small text-primary" style="cursor: pointer;">{{ __('عرض السجل') }}</summary>
                                        <div class="mt-2 small">
                                            <div class="fw-semibold mb-1">{{ __('الوصف البشري') }}</div>
                                            @php
                                                $humanLines = $log->humanChangeLines();
                                            @endphp
                                            @if (!empty($humanLines))
                                                <ul class="ps-3 mb-2">
                                                    @foreach ($humanLines as $line)
                                                        <li class="mb-1">{{ $line }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="text-muted mb-2">{{ $log->humanSummary() }}</div>
                                            @endif
                                            <div class="fw-semibold mb-1">{{ __('القيم السابقة') }}</div>
                                            @if ($log->old_values)
                                                <div class="text-muted mb-2">{{ __('يوجد تفاصيل محفوظة في السجل') }}</div>
                                            @else
                                                <div class="text-muted mb-2">—</div>
                                            @endif
                                            <div class="fw-semibold mb-1">{{ __('القيم الجديدة') }}</div>
                                            @if ($log->new_values)
                                                <div class="text-muted">{{ __('يوجد تفاصيل محفوظة في السجل') }}</div>
                                            @else
                                                <div class="text-muted">—</div>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">{{ __('لا توجد سجلات.') }}</td>
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
