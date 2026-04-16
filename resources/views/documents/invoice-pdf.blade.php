<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .header { width: 100%; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
        .logo-text { font-size: 28px; font-weight: bold; text-align: right; }
        .supply-text { text-align: right; font-size: 10px; margin-top: 5px; color: #555; }
        .info-block { width: 100%; margin-bottom: 15px; }
        .info-block td { vertical-align: top; }
        .to-text { font-size: 16px; font-weight: bold; border-bottom: 2px solid #0056b3; display: inline-block; padding-bottom: 3px; }
        .doc-meta { text-align: right; font-size: 11px; line-height: 1.6; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 20px 0; }
        .project-name { text-align: center; margin-bottom: 20px; font-weight: bold; color: #0056b3; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th, .items-table td { padding: 8px 0; }
        .items-table th { border-top: 2px solid #0056b3; border-bottom: 2px solid #0056b3; font-size: 11px; text-align: left; }
        .items-table td { border-bottom: 1px solid #eee; font-size: 11px; vertical-align: top; }
        .total-highlight { background: #000; color: #fff; padding: 5px 10px; font-weight: bold; display: inline-block; }
        .bank-details { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
        .bank-details th, .bank-details td { border: 1px solid #000; padding: 6px; text-align: left; }
        .bank-details th { width: 30%; font-weight: bold; }
        .remark { color: red; font-size: 10px; font-weight: bold; margin-top: 15px; text-decoration: underline; }
    </style>
</head>
<body>
    <table class="header" style="border:none;">
        <tr>
            <td>
                <div class="logo-text">DAYANCO®</div>
                <div class="supply-text">| Supply Chain Management |</div>
            </td>
        </tr>
    </table>

    <table class="info-block">
        <tr>
            <td style="width: 50%;">
                <div class="to-text">TO Mr. {{ $order->resolvedCustomerName() ?: '?' }} - Purchasing Manager</div>
            </td>
            <td class="doc-meta">
                <strong>Invoice Numbers:</strong> {{ $order->order_number }}<br>
                <strong>Invoice Date:</strong> {{ $generatedAt->format('F jS, Y') }}<br>
                <strong>Page:</strong> 1page
            </td>
        </tr>
    </table>

    <div class="title">INVOICE</div>
    <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 10px 0; text-align: center;">
        <span class="project-name" style="text-decoration: underline;">Project Name: {{ strtoupper($order->product_name ?: 'ITEM NAME') }}</span>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 25%;">Item</th>
                <th style="width: 45%;">Description</th>
                <th style="width: 15%; text-align: right;">Amount (USD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $generatedAt->format('M. jS') }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>
                        100% for {{ number_format($item['quantity']) }}pcs | EXW SAUDI<br>
                        - Refer to the quotation number of {{ $order->order_number }}<br>
                        - Total 0styles | 0dozens | {{ number_format($item['quantity']) }}pcs<br>
                        - Production Lead Time: around {{ $generatedAt->addDays((int)($order->production_days ?: 15))->format('F jS, Y') }}<br><br>
                        {{ $item['description'] }}
                    </td>
                    <td style="text-align: right;">{{ number_format((float) $totals['unit_price'] * (float) $item['quantity'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: right; font-size: 11px;">
                <div>Sub-total Above: <span style="text-decoration: underline; color: #0056b3;">USD {{ number_format((float) $totals['total'], 2) }}</span></div>
                <div>70% Balance: <span style="text-decoration: underline; color: #0056b3;">USD {{ number_format((float) $totals['total'] * 0.7, 2) }}</span></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="height: 10px;"></td>
        </tr>
        <tr>
            <td style="font-size: 11px;">
                <strong>{{ $generatedAt->format('F jS') }}</strong> &nbsp;&nbsp;&nbsp;&nbsp; <strong>Bank Fee</strong>
            </td>
            <td style="text-align: right; font-size: 11px;">Local Bank Charge of IMT &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 40.00</td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: right; margin-top: 20px; padding-top: 20px;">
                <span style="font-size: 14px; font-weight: bold; margin-right: 10px;">Total Amount due to this Invoice:</span> 
                <span class="total-highlight">USD {{ number_format((float) $totals['total'] + 40.00, 2) }}</span>
            </td>
        </tr>
    </table>

    <div style="font-weight: bold; font-size: 11px;">Payment Method <span style="text-decoration: underline; color: #0056b3;">( For USD remittance )</span></div>
    
    <table class="bank-details">
        <tr>
            <th>Beneficiary Name</th>
            <td>{{ $company['beneficiary_name'] ?: 'DAYANCO TRADING CO., LIMITED' }}</td>
        </tr>
        <tr>
            <th>Beneficiary Bank</th>
            <td>{{ $company['beneficiary_bank'] ?: 'ZHEJIANG CHOUZHOU COMMERCIAL BANK' }}</td>
        </tr>
        <tr>
            <th>Beneficiary Account Numbers</th>
            <td>{{ $company['account_number'] ?: 'NRA15617142010500006871' }}</td>
        </tr>
        <tr>
            <th>Beneficiary Address</th>
            <td>{{ $company['beneficiary_address'] ?: 'RM906, 9TH FLOOR, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000 P.R. CHINA' }}</td>
        </tr>
        <tr>
            <th>Bank Address</th>
            <td><span style="text-decoration: underline; color: #0056b3;">{{ $company['bank_address'] ?: 'YIWULEYUAN EAST, JIANGBIN RD, YIWU, ZHEJIANG, CHINA' }}</span></td>
        </tr>
        <tr>
            <th>SWIFT</th>
            <td>{{ $company['swift_code'] ?: 'CZCBCN2X' }}</td>
        </tr>
        <tr>
            <th>COUNTRY</th>
            <td>China</td>
        </tr>
        <tr>
            <th>PURPOSE OF PAYMENTS</th>
            <td>PURCHASE OF GOODS</td>
        </tr>
    </table>

    <div class="remark">REMARK: PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING, THANK YOU!</div>
</body>
</html>
