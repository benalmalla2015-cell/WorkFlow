<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>عرض سعر {{ $order->order_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            font-size: 11px;
            margin: 0;
            padding: 18px 22px;
            direction: rtl;
        }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .brand-block { width: 64%; vertical-align: top; }
        .doc-block { width: 36%; vertical-align: top; text-align: left; }
        .brand { font-size: 28px; font-weight: 900; color: #0f172a; letter-spacing: 1px; }
        .brand-sub { font-size: 10px; color: #475569; margin-top: 3px; }
        .brand-ar { font-size: 12px; font-weight: 700; margin-top: 5px; }
        .company-meta, .doc-meta { font-size: 9px; color: #475569; line-height: 1.8; }
        .doc-card { border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 12px 14px; }
        .doc-name { font-size: 20px; font-weight: 900; color: #0f172a; margin-bottom: 8px; }
        .section { border: 1px solid #dbe2ea; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; background: #f8fafc; }
        .section-title { font-size: 12px; font-weight: 800; margin-bottom: 8px; color: #0f172a; }
        .customer-table, .items-table, .summary-table { width: 100%; border-collapse: collapse; }
        .customer-table td { padding: 4px 0; vertical-align: top; }
        .label { width: 22%; color: #64748b; font-weight: 700; }
        .items-table { margin-bottom: 14px; }
        .items-table th { background: #0f172a; color: #fff; padding: 9px 7px; font-size: 10px; text-align: center; }
        .items-table td { border: 1px solid #dbe2ea; padding: 8px 7px; font-size: 10px; text-align: center; vertical-align: top; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .summary-wrap { width: 42%; margin-right: auto; }
        .summary-table td { border: 1px solid #dbe2ea; padding: 7px 10px; font-size: 10px; }
        .summary-table .grand td { background: #0f172a; color: #fff; font-weight: 800; }
        .notes { margin-top: 18px; border: 1px dashed #94a3b8; border-radius: 12px; padding: 10px 12px; font-size: 9px; line-height: 1.9; color: #475569; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="brand-block">
                <div class="brand">DAYANCO®</div>
                <div class="brand-sub">TRADING CO. LIMITED | Supply Chain Management</div>
                <div class="brand-ar">شركة ديانكو التجارية المحدودة</div>
                <div class="company-meta">
                    {{ $company['address'] ?: 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA' }}<br>
                    ATTN: {{ $company['attn'] ?: 'Mr. Abdulmalek' }} | {{ $company['phone'] ?: '+86 188188 45411' }} | {{ $company['email'] ?: 'team@dayancoofficial.com' }}
                </div>
            </td>
            <td class="doc-block">
                <div class="doc-card">
                    <div class="doc-name">QUOTATION</div>
                    <div class="doc-meta">
                        <strong>رقم العرض:</strong> {{ $documentOrder['order_number'] }}<br>
                        <strong>التاريخ:</strong> {{ $generatedAt->format('Y-m-d') }}<br>
                        <strong>صالح حتى:</strong> {{ $generatedAt->copy()->addDays(21)->format('Y-m-d') }}<br>
                        <strong>العملة:</strong> {{ $totals['currency'] }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">بيانات العميل</div>
        <table class="customer-table">
            <tr>
                <td class="label">العميل</td>
                <td>{{ $documentOrder['customer_name'] }}</td>
            </tr>
            <tr>
                <td class="label">مرجع الطلب</td>
                <td>{{ $documentOrder['order_number'] }}</td>
            </tr>
            <tr>
                <td class="label">مدة الإنتاج</td>
                <td>{{ $documentOrder['production_days'] }} يوم</td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 28%;">الصنف / Item</th>
                <th>الوصف / Description</th>
                <th style="width: 12%;">الكمية / Qty</th>
                <th style="width: 16%;">سعر البيع / Sales Price</th>
                <th style="width: 18%;">الإجمالي / Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: right;">{{ $item['item_name'] }}</td>
                    <td style="text-align: right;">{{ $item['description'] }}</td>
                    <td>{{ number_format($item['quantity']) }}</td>
                    <td>{{ $totals['currency'] }} {{ number_format((float) $item['sales_price'], 2) }}</td>
                    <td>{{ $totals['currency'] }} {{ number_format((float) $item['line_total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td>الإجمالي قبل الضريبة</td>
                <td style="text-align: left;">{{ $totals['currency'] }} {{ number_format((float) $totals['subtotal'], 2) }}</td>
            </tr>
            <tr>
                <td>الضريبة</td>
                <td style="text-align: left;">{{ number_format((float) $totals['tax_rate'], 2) }}% ({{ $totals['currency'] }} {{ number_format((float) $totals['tax_amount'], 2) }})</td>
            </tr>
            <tr class="grand">
                <td>الإجمالي النهائي</td>
                <td style="text-align: left;">{{ $totals['currency'] }} {{ number_format((float) $totals['grand_total'], 2) }}</td>
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
