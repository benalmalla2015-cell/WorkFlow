@php
    $cssPath = base_path('public/css/pdf-documents.css');
    $logoPath = base_path('public/images/dayanco-logo.svg');
    $pdfCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';
    $logoSvg = file_exists($logoPath) ? file_get_contents($logoPath) : '';
    $logoMarkup = $logoSvg !== '' ? $logoSvg : '<div class="brand-fallback">DAYANCO</div>';
    $bankFee = 40.00;
    $balanceDue = round((float) $totals['grand_total'] * 0.70, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $documentOrder['order_number'] }}</title>
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

        <div class="recipient-row"><span class="label">TO</span> Mr. {{ $documentOrder['customer_name'] }} - Purchasing Manager</div>

        <table class="document-meta-table">
            <tr>
                <td class="document-meta-left"></td>
                <td class="document-meta-right">
                    <span class="meta-label">Invoice Number:</span> {{ $documentOrder['order_number'] }}<br>
                    <span class="meta-label">Invoice Date:</span> {{ $documentOrder['issue_date_long'] }}<br>
                    <span class="meta-label">Page:</span> 1/page
                </td>
            </tr>
        </table>

        <div class="document-title">INVOICE</div>
        <hr class="rule-strong">

        <div class="project-line"><strong>Project Name / ITEM NAME:</strong> {{ $documentOrder['product_name'] }}</div>

        <table class="invoice-items">
            <thead>
                <tr>
                    <th style="width:14%;">Date</th>
                    <th style="width:20%;">Item</th>
                    <th>Description</th>
                    <th style="width:18%;">Amount ({{ $totals['currency'] }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $generatedAt->format('M d') }}</td>
                        <td>{{ $item['item_name'] }}</td>
                        <td>
                            100% for {{ number_format((float) $item['quantity']) }} pcs<br>
                            - Refer to quotation number {{ $documentOrder['order_number'] }}<br>
                            - Product Code: {{ $item['product_code'] ?: 'N/A' }}<br>
                            - Specifications: {{ $item['description'] ?: 'As approved quotation' }}<br>
                            - Supplier: {{ $item['supplier_name'] ?: 'DAYANCO source' }}<br>
                            - Production Lead Time: around {{ $documentOrder['production_days'] }} days
                        </td>
                        <td class="amount">{{ number_format((float) $item['line_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="placeholder">No invoice items available.</td>
                    </tr>
                @endforelse
                <tr class="soft-total">
                    <td colspan="3" class="align-right">Sub-total Above {{ $totals['currency'] }}</td>
                    <td class="amount">{{ number_format((float) $totals['subtotal'], 2) }}</td>
                </tr>
                <tr class="soft-total">
                    <td colspan="3" class="align-right">70% Balance {{ $totals['currency'] }}</td>
                    <td class="amount">{{ number_format($balanceDue, 2) }}</td>
                </tr>
                <tr>
                    <td>{{ $generatedAt->format('M d') }}</td>
                    <td>Bank Fee</td>
                    <td>Local Bank Charge of IMT</td>
                    <td class="amount">{{ number_format($bankFee, 2) }}</td>
                </tr>
                <tr class="grand-row">
                    <td colspan="3" class="align-right">Total Amount due to This Invoice:</td>
                    <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $totals['grand_total'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="payment-section-title">Payment Method ( For USD remittance )</div>

        <table class="payment-table">
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
                <td>{{ $company['bank_address'] ?: 'YIWU, ZHEJIANG, CHINA' }}</td>
            </tr>
            <tr>
                <th>SWIFT</th>
                <td>{{ $company['swift_code'] ?: 'CZCBCN2X' }}</td>
            </tr>
            <tr>
                <th>COUNTRY</th>
                <td>{{ $company['country'] ?: 'China' }}</td>
            </tr>
            <tr>
                <th>PURPOSE OF PAYMENTS</th>
                <td class="bank-purpose">{{ $company['payment_purpose'] ?: 'PURCHASE OF GOODS' }}</td>
            </tr>
        </table>

        <div class="remark-box">REMARK: PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING. THANK YOU.</div>

        <table class="footer-table">
            <tr>
                <td>
                    <div class="footer-note"><strong>Sales Representative:</strong> {{ $salesRepresentative ?: 'Sales Team' }}</div>
                    <div class="footer-note"><strong>Generated:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
                </td>
                <td style="width:36%; text-align:right;">
                    <div class="verification-box"><strong>Verification:</strong> {{ $verificationUrl }}</div>
                    <div class="small-muted">DAYANCO commercial invoice generated from WorkFlow.</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
