@extends('layouts.app')

@section('title', 'مراجعة الطلب | WorkFlow')

@php
    $pricingComplete = $order->hasCompleteFactoryItemPricing() && $order->production_days;
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">مراجعة الطلب {{ $order->order_number }}</h1>
            <div class="text-muted">الطلب في مرحلة اعتماد الإدارة النهائية بعد استلام بيانات المصنع.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status status-{{ $order->status }}">{{ $order->status_label }}</span>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">رجوع</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card form-card mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">الملخص التجاري</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 bg-light">
                                <div class="text-muted small">العميل</div>
                                <div class="fw-semibold">{{ optional($order->customer)->full_name ?: '—' }}</div>
                                <div class="text-muted small mt-3">العنوان</div>
                                <div>{{ optional($order->customer)->address ?: '—' }}</div>
                                <div class="text-muted small mt-3">التواصل</div>
                                <div>{{ optional($order->customer)->phone ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 bg-light">
                                <div class="text-muted small">موظف المبيعات</div>
                                <div class="fw-semibold">{{ optional($order->salesUser)->name ?: '—' }}</div>
                                <div class="text-muted small mt-3">المنتج</div>
                                <div>{{ $order->product_name }}</div>
                                <div class="text-muted small mt-3">الكمية</div>
                                <div>{{ number_format($order->quantity) }}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded-4 p-3 bg-light">
                                <div class="text-muted small mb-2">المواصفات الفنية</div>
                                <div>{{ $order->specifications ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">المرفقات</h2>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">مرفقات المبيعات</div>
                            <div class="list-group list-group-flush">
                                @forelse ($order->attachments->where('type', 'sales_upload') as $attachment)
                                    <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action">{{ $attachment->original_name }}</a>
                                @empty
                                    <div class="text-muted">لا توجد مرفقات.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">مرفقات المصنع</div>
                            <div class="list-group list-group-flush">
                                @forelse ($order->attachments->where('type', 'factory_upload') as $attachment)
                                    <a href="{{ route('attachments.download', $attachment) }}" class="list-group-item list-group-item-action">{{ $attachment->original_name }}</a>
                                @empty
                                    <div class="text-muted">لا توجد مرفقات.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card form-card">
                <div class="card-body p-4">
                    <h2 class="h5 section-title">الهامش والاعتماد</h2>
                    <div class="border rounded-4 p-3 bg-light mb-4">
                        <div class="text-muted small">عدد العناصر المسعّرة</div>
                        <div class="fw-semibold">{{ count($pricingSummary['line_items'] ?? []) }}</div>
                        <div class="text-muted small mt-3">إجمالي تكلفة المصنع</div>
                        <div class="fw-semibold text-danger">${{ number_format((float) ($pricingSummary['total_factory_cost'] ?? 0), 2) }}</div>
                        <div class="text-muted small mt-3">مدة الإنتاج</div>
                        <div>{{ $order->production_days ?: '—' }} يوم</div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>العنصر</th>
                                    <th>الكمية</th>
                                    <th>المورد</th>
                                    <th>الكود</th>
                                    <th>سعر الوحدة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (($pricingSummary['line_items'] ?? []) as $item)
                                    <tr>
                                        <td>{{ $item['item_name'] }}</td>
                                        <td>{{ number_format((int) $item['quantity']) }}</td>
                                        <td>{{ $item['supplier_name'] ?: '—' }}</td>
                                        <td>{{ $item['product_code'] ?: '—' }}</td>
                                        <td>${{ number_format((float) $item['unit_cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @unless ($pricingComplete)
                        <div class="alert alert-warning border-0 shadow-sm">لا يمكن اعتماد الطلب قبل تعبئة المورد وكود المنتج وسعر الوحدة لكل عنصر، مع تحديد مدة الإنتاج.</div>
                    @endunless

                    <form method="POST" action="{{ route('admin.orders.approve', $order) }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">هامش الربح (%)</label>
                            <input type="number" min="0" max="500" step="0.01" name="profit_margin_percentage" id="profit_margin_percentage" class="form-control form-control-lg" value="{{ old('profit_margin_percentage', $defaultMargin) }}" @disabled(!$pricingComplete) required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">السعر النهائي المتوقع</label>
                            <input type="text" id="final_price_preview" class="form-control form-control-lg" value="${{ number_format((float) (($pricingSummary['sales_total'] ?? 0) > 0 ? $pricingSummary['sales_total'] : 0), 2) }}" disabled>
                        </div>
                        <div class="col-12 d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" @disabled(!$pricingComplete)>اعتماد الطلب نهائيًا</button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <form method="POST" action="{{ route('admin.orders.request-adjustment', $order) }}" class="mb-3">
                        @csrf
                        <label class="form-label">سبب طلب التعديل</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="اكتب سبب إعادة الطلب للمصنع أو للفريق المختص..."></textarea>
                        <button type="submit" class="btn btn-outline-warning w-100 mt-3">طلب تعديل إضافي</button>
                    </form>

                    <form method="POST" action="{{ route('admin.orders.reject', $order) }}">
                        @csrf
                        <label class="form-label">سبب الرفض</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="يمكنك توضيح سبب إعادة الطلب للمبيعات..."></textarea>
                        <button type="submit" class="btn btn-outline-danger w-100 mt-3">رفض وإعادة الطلب</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const marginInput = document.getElementById('profit_margin_percentage');
        const preview = document.getElementById('final_price_preview');
        const totalFactoryCost = {{ (float) ($pricingSummary['total_factory_cost'] ?? 0) }};

        if (marginInput && preview) {
            const recalculate = () => {
                const margin = parseFloat(marginInput.value || 0);
                const total = totalFactoryCost * (1 + (margin / 100));
                preview.value = `$${total.toFixed(2)}`;
            };

            marginInput.addEventListener('input', recalculate);
            recalculate();
        }
    </script>
@endpush
