@php
    $cssPath = base_path('public/css/pdf-documents.css');
    $logoPath = base_path('public/images/dayanco-logo.svg');
    $pdfCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';
    $logoSvg = file_exists($logoPath) ? file_get_contents($logoPath) : '';
    $logoMarkup = $logoSvg !== '' ? $logoSvg : '<div class="brand-fallback">DAYANCO</div>';
    $placeholder = '—';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $documentOrder['order_number'] }}</title>
    <style>{!! $pdfCss !!}</style>
</head>
<body>
    <div class="page-shell">
        <table class="brand-header">
            <tr>
                <td class="brand-spacer"></td>
                <td class="brand-identity">
                    <div class="brand-logo">{!! $logoMarkup !!}</div>
                    <div class="ar-line">شركة ديانكو التجارية المحدودة</div>
                </td>
            </tr>
        </table>

        <div class="company-lines">
            <div class="company-address-line">{{ $company['address'] ?: 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA' }}</div>
            <div class="company-contact-line">ATTN: {{ $company['attn'] ?: 'Mr. Abdulmalek' }} | China Mobile: {{ $company['phone'] ?: '+86 188188 45411' }} | E-mail: {{ $company['email'] ?: 'team@dayancoofficial.com' }}</div>
        </div>

        <div class="product-hero">{{ $documentOrder['product_name'] }} _Quotations</div>

        <div class="recipient-row"><span class="label">TO</span> Mr. {{ $documentOrder['customer_name'] }} - Purchasing Manager</div>

        <table class="document-meta-table">
            <tr>
                <td class="document-meta-left">
                    <span class="meta-label">File No.:</span> {{ $documentOrder['file_number'] }}
                </td>
                <td class="document-meta-right">
                    <span class="meta-label">Quotation Date:</span> {{ $documentOrder['issue_date_long'] }}
                </td>
            </tr>
            <tr>
                <td class="document-meta-left"></td>
                <td class="document-meta-right">
                    <div class="meta-highlight">Quotation Valid Date: {{ $documentOrder['valid_until'] }}</div>
                </td>
            </tr>
        </table>

        <table class="quote-grid">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3%;">No.</th>
                    <th rowspan="2" style="width:14%;">Item Name</th>
                    <th rowspan="2" style="width:9%;">Reference Pic</th>
                    <th rowspan="2" style="width:5%;">HS Code</th>
                    <th rowspan="2" style="width:6%;">Barcode</th>
                    <th rowspan="2" style="width:6%;">Material</th>
                    <th rowspan="2" style="width:4%;">Color</th>
                    <th rowspan="2" style="width:4%;">Size</th>
                    <th colspan="2" style="width:10%;">Packaging</th>
                    <th rowspan="2" style="width:6%;">Container</th>
                    <th colspan="2" style="width:7%;">Qty</th>
                    <th rowspan="2" style="width:7%;">Unit Cost<br>USD</th>
                    <th rowspan="2" style="width:8%;">Sub-total<br>USD</th>
                    <th rowspan="2" style="width:6%;">Lead Time<br>Days</th>
                </tr>
                <tr class="subhead">
                    <th>Qty / Carton</th>
                    <th>Carton Size</th>
                    <th>Pallets</th>
                    <th>PCS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="align-center">{{ $item['line'] ?: $loop->iteration }}</td>
                        <td>{{ $item['item_name'] }}@if($item['supplier_name'])<br><span class="small-muted">{{ $item['supplier_name'] }}</span>@endif</td>
                        <td class="placeholder">Image Ref</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $item['product_code'] ?: $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ $placeholder }}</td>
                        <td class="align-center">{{ number_format((float) $item['quantity']) }}</td>
                        <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $item['sales_price'], 2) }}</td>
                        <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $item['line_total'], 2) }}</td>
                        <td class="align-center">{{ $documentOrder['production_days'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="placeholder">No quotation items available.</td>
                    </tr>
                @endforelse
                <tr class="accent-total">
                    <td colspan="13" class="align-right">total</td>
                    <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $totals['unit_price'], 2) }}</td>
                    <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $totals['grand_total'], 2) }}</td>
                    <td class="align-center">{{ $documentOrder['production_days'] }}</td>
                </tr>
            </tbody>
        </table>

        <table class="footer-table">
            <tr>
                <td>
                    <div class="footer-note"><strong>Sales Representative:</strong> {{ $salesRepresentative ?: 'Sales Team' }}</div>
                    <div class="footer-note"><strong>Generated:</strong> {{ $generatedAt->format('Y-m-d H:i') }} | <strong>Order:</strong> {{ $documentOrder['order_number'] }}</div>
                </td>
                <td style="width:36%; text-align:right;">
                    <div class="verification-box"><strong>Verification:</strong> {{ $verificationUrl }}</div>
                    <div class="small-muted">Commercial quotation generated from WorkFlow with DAYANCO visual identity.</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
