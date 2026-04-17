<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>عرض سعر {{ $order->order_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 11px;
            margin: 0;
            padding: 24px 28px;
            direction: rtl;
        }
        .header { width: 100%; border-bottom: 3px solid #0f172a; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { font-size: 30px; font-weight: 900; color: #0f172a; letter-spacing: 2px; }
        .brand-sub { font-size: 10px; color: #64748b; margin-top: 2px; }
        .brand-ar { font-size: 12px; color: #334155; margin-top: 4px; font-weight: 700; }
        .company-meta { font-size: 9px; color: #475569; margin-top: 8px; line-height: 1.7; }
        .title { text-align: center; font-size: 22px; font-weight: 900; margin: 16px 0; padding: 8px 0; border-top: 2px solid #e2e8f0; border-bottom: 2px solid #e2e8f0; color: #0f172a; }
        .title small { display: block; margin-top: 6px; font-size: 13px; color: #64748b; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc; vertical-align: top; }
        .meta-label { font-weight: 700; color: #334155; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table th { background: #0f172a; color: #fff; padding: 8px 6px; font-size: 10px; text-align: center; }
        .items-table td { border: 1px solid #cbd5e1; padding: 7px 6px; font-size: 10px; text-align: center; vertical-align: top; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .total-row td { font-weight: 700; background: #e2e8f0; }
        .summary { width: 48%; margin-right: auto; border: 2px solid #0f172a; border-radius: 8px; padding: 10px; }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 5px 6px; font-size: 11px; }
        .summary .grand { border-top: 2px solid #0f172a; font-weight: 900; color: #0f172a; font-size: 14px; }
        .notes { margin-top: 20px; padding: 12px; border: 1px solid #94a3b8; border-radius: 8px; font-size: 10px; color: #475569; line-height: 1.8; }
        .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #64748b; border-top: 2px solid #0f172a; padding-top: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="brand">DAYANCO®</div>
                <div class="brand-sub">TRADING CO. LIMITED | Supply Chain Management</div>
                <div class="brand-ar">شركة ديانكو التجارية المحدودة</div>
                <div class="company-meta">
                    {{ $company['address'] ?: 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA' }}<br>
                    ATTN: {{ $company['attn'] ?: 'Mr. Abdulmalek' }} | {{ $company['phone'] ?: '+86 188188 45411' }} | {{ $company['email'] ?: 'team@dayancoofficial.com' }}
                </div>
            </td>
            <td style="width: 40%; text-align: left; vertical-align: top;">
                <div class="meta-label">عرض سعر / QUOTATION</div>
                <div class="company-meta">
                    <strong>رقم العرض:</strong> {{ $order->order_number }}<br>
                    <strong>تاريخ الإصدار:</strong> {{ $generatedAt->format('Y-m-d') }}<br>
                    <strong>صالح حتى:</strong> {{ $generatedAt->copy()->addDays(21)->format('Y-m-d') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="title">
        QUOTATION - عرض سعر
        <small>{{ $order->product_name ?: 'المنتج' }}</small>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <span class="meta-label">العميل / TO:</span><br>
                {{ $order->resolvedCustomerName() ?: 'غير محدد' }}
            </td>
            <td style="width: 50%;">
                <span class="meta-label">مدة الإنتاج:</span> {{ $order->production_days ?: '15' }} يوم<br>
                <span class="meta-label">العملة:</span> {{ $totals['currency'] }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 28%;">المنتج / Item</th>
                <th style="width: 24%;">الوصف / Description</th>
                <th style="width: 10%;">الكمية / Qty</th>
                <th style="width: 14%;">سعر الوحدة / Unit Price</th>
                <th style="width: 12%;">الإجمالي / Subtotal</th>
                <th style="width: 6%;">المدة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: right;">{{ $item['item_name'] }}</td>
                    <td style="text-align: right;">{{ $item['description'] }}</td>
                    <td>{{ number_format($item['quantity']) }}</td>
                    <td>{{ $totals['currency'] }} {{ number_format((float) $totals['unit_price'], 2) }}</td>
                    <td>{{ $totals['currency'] }} {{ number_format((float) $totals['unit_price'] * (float) $item['quantity'], 2) }}</td>
                    <td>{{ $order->production_days ?: '15' }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">الإجمالي / Total</td>
                <td>{{ number_format($totals['quantity']) }}</td>
                <td>{{ $totals['currency'] }} {{ number_format((float) $totals['unit_price'], 2) }}</td>
                <td>{{ $totals['currency'] }} {{ number_format((float) $totals['subtotal'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td class="meta-label">الإجمالي قبل الضريبة</td>
                <td style="text-align: left;">{{ $totals['currency'] }} {{ number_format((float) $totals['subtotal'], 2) }}</td>
            </tr>
            <tr>
                <td class="meta-label">الضريبة</td>
                <td style="text-align: left;">{{ number_format((float) $totals['tax_rate'], 2) }}% ({{ $totals['currency'] }} {{ number_format((float) $totals['tax_amount'], 2) }})</td>
            </tr>
            <tr>
                <td class="grand">الإجمالي النهائي</td>
                <td class="grand" style="text-align: left;">{{ $totals['currency'] }} {{ number_format((float) $totals['grand_total'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="notes">
        <strong>الشروط والملاحظات / Terms & Notes</strong><br>
        - الأسعار المذكورة أعلاه مبنية على البيانات الحالية للطلب والمواصفات المعتمدة.<br>
        - مدة صلاحية عرض السعر 21 يومًا من تاريخ الإصدار.<br>
        - لا يتم احتساب أي رسوم إضافية إلا إذا نص النظام أو الاتفاق التجاري على ذلك.<br>
        - This quotation is valid for 21 days from the issue date.<br>
        - Taxes are applied only if configured in the company settings.
    </div>

    <div class="footer">
        DAYANCO TRADING CO. LIMITED | شركة ديانكو التجارية المحدودة<br>
        {{ $company['email'] ?: 'team@dayancoofficial.com' }} | {{ $company['phone'] ?: '+86 188188 45411' }}
    </div>
</body>
</html>
