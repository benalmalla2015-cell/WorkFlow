<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $order->order_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
            font-size: 11px;
            margin: 0;
            padding: 20px;
        }
        .header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-text { font-size: 28px; font-weight: bold; text-align: right; }
        .supply-text { text-align: right; font-size: 10px; margin-top: 5px; color: #555; }
        .address-text { text-align: right; font-size: 9px; margin-top: 10px; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 20px 0; }
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 3px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 6px; text-align: center; }
        .items-table th { background: #e6f0fa; font-size: 9px; }
        .items-table td { font-size: 9px; }
        .total-row td { font-weight: bold; background: #f8f9fa; }
        .remarks { margin-top: 20px; font-size: 10px; border: 1px solid #000; padding: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="logo-text">DAYANCO®</div>
                <div class="supply-text">| Supply Chain Management |</div>
                <div class="address-text">
                    {{ $company['address'] ?: 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA' }}<br>
                    ATTN: {{ $company['attn'] ?: 'Mr. Abdulmalek' }} | Mobile: {{ $company['phone'] ?: '+86 188188 45411' }} | E-mail: {{ $company['email'] ?: 'team@dayancoofficial.com' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="title">{{ $order->product_name ?: 'item name' }} _Quotations</div>

    <table class="info-table">
        <tr>
            <td style="font-size: 14px; font-weight: bold;">TO Mr. {{ $order->resolvedCustomerName() ?: '?' }} - Purchasing Manager</td>
        </tr>
        <tr>
            <td><strong>File No.:</strong> {{ $order->order_number }}</td>
        </tr>
    </table>

    <table class="info-table" style="border: 2px solid #228b22; margin-bottom: 0;">
        <tr>
            <td style="padding: 5px;"><strong>Quotation Date:</strong> {{ $generatedAt->format('F jS, Y') }}</td>
            <td style="text-align: right; padding: 5px;"><strong>Quotation Valid Date:</strong> {{ $generatedAt->addDays(21)->format('F jS, Y') }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th rowspan="2">No.</th>
                <th rowspan="2">Item Name</th>
                <th rowspan="2">Reference Picture</th>
                <th rowspan="2">HS CODE</th>
                <th rowspan="2">Barcode</th>
                <th rowspan="2">Material</th>
                <th rowspan="2">Color</th>
                <th rowspan="2">Size</th>
                <th colspan="2">Packaging</th>
                <th rowspan="2">Loading Container</th>
                <th colspan="2">Quantities</th>
                <th rowspan="2">Unit Cost<br>EXW China<br>USD</th>
                <th rowspan="2">Sub-total Cost<br>(EXW China)<br>USD</th>
                <th rowspan="2">Production<br>Lead Time<br>DAYS</th>
            </tr>
            <tr>
                <th>Quantities/Carton</th>
                <th>Carton Size</th>
                <th>PALLETS</th>
                <th>PCS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['item_name'] }}<br><small>{{ $item['description'] }}</small></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>{{ number_format($item['quantity']) }}</td>
                    <td>{{ number_format((float) $totals['unit_price'], 2) }}</td>
                    <td>{{ number_format((float) $totals['unit_price'] * (float) $item['quantity'], 2) }}</td>
                    <td>{{ $order->production_days ?: '15' }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="12" style="text-align: right;">total</td>
                <td>{{ number_format($totals['quantity']) }}</td>
                <td></td>
                <td>{{ number_format((float) $totals['total'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
