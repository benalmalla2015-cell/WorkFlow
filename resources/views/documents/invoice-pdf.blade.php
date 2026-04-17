<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فاتورة {{ $order->order_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 11px;
            margin: 0;
            padding: 24px 28px;
            direction: rtl;
        }
        .header { width: 100%; border-bottom: 3px solid #0f172a; padding-bottom: 14px; margin-bottom: 16px; }
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
        .summary { width: 48%; margin-right: auto; border: 2px solid #0f172a; border-radius: 8px; padding: 10px; margin-bottom: 16px; }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 5px 6px; font-size: 11px; }
        .summary .grand { border-top: 2px solid #0f172a; font-weight: 900; color: #0f172a; font-size: 14px; }
        .bank-details { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        .bank-details th, .bank-details td { border: 1px solid #94a3b8; padding: 6px 8px; text-align: right; }
        .bank-details th { width: 32%; font-weight: 700; background: #f1f5f9; color: #334155; }
        .remark { color: #dc2626; font-size: 10px; font-weight: 700; margin-top: 15px; border: 2px solid #dc2626; padding: 10px; border-radius: 8px; }
        .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #64748b; border-top: 2px solid #0f172a; padding-top: 10px; }
    </style>
</head>
<body>
    <table class="header" style="border:none;">
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
                <div class="meta-label">فاتورة / INVOICE</div>
                <div class="company-meta">
                    <strong>رقم الفاتورة:</strong> {{ $order->order_number }}<br>
                    <strong>تاريخ الإصدار:</strong> {{ $generatedAt->format('Y-m-d') }}<br>
                    <strong>العملة:</strong> {{ $totals['currency'] }}
                </div>
            </td>
        </tr>
    </table>

    <div class="title">
        INVOICE - فاتورة
        <small>{{ strtoupper($order->product_name ?: 'المنتج') }}</small>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <span class="meta-label">العميل / TO:</span><br>
                {{ $order->resolvedCustomerName() ?: 'غير محدد' }} - Purchasing Manager
            </td>
            <td style="width: 50%;">
                <span class="meta-label">مدة الإنتاج:</span> {{ $order->production_days ?: '15' }} يوم<br>
                <span class="meta-label">مرجع الطلب:</span> {{ $order->order_number }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%;">التاريخ</th>
                <th style="width: 25%;">المنتج / Item</th>
                <th style="width: 35%;">الوصف / Description</th>
                <th style="width: 10%;">الكمية</th>
                <th style="width: 20%;">القيمة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $generatedAt->format('Y-m-d') }}</td>
                    <td style="text-align: right;">{{ $item['item_name'] }}</td>
                    <td style="text-align: right;">
                        Ref: {{ $order->order_number }}<br>
                        Lead Time: {{ $order->production_days ?: '15' }} days<br>
                        {{ $item['description'] }}
                    </td>
                    <td>{{ number_format($item['quantity']) }}</td>
                    <td>{{ $totals['currency'] }} {{ number_format((float) $totals['unit_price'] * (float) $item['quantity'], 2) }}</td>
                </tr>
            @endforeach
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

    <div style="font-weight: 700; font-size: 11px; margin-bottom: 6px;">طريقة الدفع / Payment Method</div>
    
    <table class="bank-details">
        <tr>
            <th>اسم المستفيد / Beneficiary Name</th>
            <td>{{ $company['beneficiary_name'] ?: 'DAYANCO TRADING CO., LIMITED' }}</td>
        </tr>
        <tr>
            <th>البنك / Beneficiary Bank</th>
            <td>{{ $company['beneficiary_bank'] ?: 'ZHEJIANG CHOUZHOU COMMERCIAL BANK' }}</td>
        </tr>
        <tr>
            <th>رقم الحساب / Account No.</th>
            <td>{{ $company['account_number'] ?: 'NRA15617142010500006871' }}</td>
        </tr>
        <tr>
            <th>عنوان المستفيد / Beneficiary Address</th>
            <td>{{ $company['beneficiary_address'] ?: 'RM906, 9TH FLOOR, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000 P.R. CHINA' }}</td>
        </tr>
        <tr>
            <th>عنوان البنك / Bank Address</th>
            <td>{{ $company['bank_address'] ?: 'YIWULEYUAN EAST, JIANGBIN RD, YIWU, ZHEJIANG, CHINA' }}</td>
        </tr>
        <tr>
            <th>SWIFT</th>
            <td>{{ $company['swift_code'] ?: 'CZCBCN2X' }}</td>
        </tr>
        <tr>
            <th>الدولة / Country</th>
            <td>China</td>
        </tr>
        <tr>
            <th>الغرض / Purpose</th>
            <td>PURCHASE OF GOODS</td>
        </tr>
    </table>

    <div class="remark">يرجى استخدام الاسم الكامل للمستفيد أعلاه عند التحويل. شكراً لكم.<br>REMARK: PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING. THANK YOU!</div>

    <div class="footer">
        DAYANCO TRADING CO. LIMITED | شركة ديانكو التجارية المحدودة<br>
        {{ $company['email'] ?: 'team@dayancoofficial.com' }} | {{ $company['phone'] ?: '+86 188188 45411' }}
    </div>
</body>
</html>
