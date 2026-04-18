@php
    $cssPath = base_path('public/css/pdf-documents.css');
    $logoPath = base_path('public/images/dayanco-logo.svg');
    $pdfCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';
    $logoSvg = file_exists($logoPath) ? file_get_contents($logoPath) : '';
    $logoMarkup = $logoSvg !== '' ? $logoSvg : '<div class="brand-fallback">DAYANCO</div>';
    $bankFee = 40.00;
    $balanceDue = round((float) $totals['grand_total'] * 0.70, 2);
    $invoiceTotalDue = round($balanceDue + $bankFee, 2);
    $paymentDetails = [
        'beneficiary_name' => $company['beneficiary_name'] ?: 'DAYANCO',
        'beneficiary_bank' => $company['beneficiary_bank'] ?: 'ZHEJIANG CHOUZHOU COMMERCIAL BANK',
        'account_number' => $company['account_number'] ?: 'NRA15617142010500006871',
        'beneficiary_address' => $company['beneficiary_address'] ?: 'RM906, 9/F FLOOR, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000 P.R. CHINA',
        'bank_address' => $company['bank_address'] ?: 'YIWU LEYUAN EAST, JIANGBIN RD, YIWU, ZHEJIANG, CHINA',
        'swift_code' => $company['swift_code'] ?: 'CZCBCN2X',
        'country' => $company['country'] ?: 'China',
        'payment_purpose' => $company['payment_purpose'] ?: 'PURCHASE OF GOODS',
    ];
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
                </td>
            </tr>
        </table>

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

        <div class="project-line"><strong>Project Name:</strong> {{ $documentOrder['product_name'] }}</div>

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
                            - Specifications: {{ $item['description'] ?: 'As approved quotation' }}<br>
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
                    <td class="amount"><span class="amount-chip">{{ $totals['currency'] }}{{ number_format($invoiceTotalDue, 2) }}</span></td>
                </tr>
            </tbody>
        </table>

        <div class="payment-section-title">Payment Method ( For USD remittance )</div>

        <table class="payment-table">
            <tr>
                <th>Beneficiary Name</th>
                <td>{{ $paymentDetails['beneficiary_name'] }}</td>
            </tr>
            <tr>
                <th>Beneficiary Bank</th>
                <td>{{ $paymentDetails['beneficiary_bank'] }}</td>
            </tr>
            <tr>
                <th>Beneficiary Account Numbers</th>
                <td>{{ $paymentDetails['account_number'] }}</td>
            </tr>
            <tr>
                <th>Beneficiary Address</th>
                <td>{{ $paymentDetails['beneficiary_address'] }}</td>
            </tr>
            <tr>
                <th>Bank Address</th>
                <td>{{ $paymentDetails['bank_address'] }}</td>
            </tr>
            <tr>
                <th>SWIFT</th>
                <td>{{ $paymentDetails['swift_code'] }}</td>
            </tr>
            <tr>
                <th>COUNTRY</th>
                <td>{{ $paymentDetails['country'] }}</td>
            </tr>
            <tr>
                <th>PURPOSE OF PAYMENTS</th>
                <td class="bank-purpose">{{ $paymentDetails['payment_purpose'] }}</td>
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
                    @if ($verificationQr)
                        <div class="verification-qr"><img src="{{ $verificationQr }}" alt="Verification QR"></div>
                    @endif
                    <div class="verification-caption">Scan to verify</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
