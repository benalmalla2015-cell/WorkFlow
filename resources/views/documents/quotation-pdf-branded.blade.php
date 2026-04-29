@php
    $cssPath = base_path('public/css/pdf-documents.css');
    $logoPath = base_path('public/images/dayanco-logo.svg');
    $pdfCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';
    $logoSvg = file_exists($logoPath) ? file_get_contents($logoPath) : '';
    $logoMarkup = $logoSvg !== '' ? $logoSvg : '<div class="brand-fallback">DAYANCO</div>';
    $placeholder = '—';
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
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Quotation') }} {{ $documentOrder['order_number'] }}</title>
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

        <div class="company-lines">
            <div class="company-address-line">{{ $company['address'] ?: 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA' }}</div>
            <div class="company-contact-line">{{ __('ATTN') }}: {{ $company['attn'] ?: 'Mr. Abdulmalek' }} | {{ __('China Mobile') }}: {{ $company['phone'] ?: '+86 188188 45411' }} | {{ __('E-mail') }}: {{ $company['email'] ?: 'team@dayancoofficial.com' }}</div>
        </div>

        <div class="product-hero">{{ $documentOrder['product_name'] }} {{ __('_Quotations') }}</div>

        <div class="recipient-row"><span class="label">{{ __('TO') }}</span> {{ __('Mr.') }} {{ $documentOrder['customer_name'] }} - {{ __('Purchasing Manager') }}</div>

        <table class="document-meta-table">
            <tr>
                <td class="document-meta-left">
                    <span class="meta-label">{{ __('File No.:') }}</span> {{ $documentOrder['file_number'] }}
                </td>
                <td class="document-meta-right">
                    <span class="meta-label">{{ __('Quotation Date:') }}</span> {{ $documentOrder['issue_date_long'] }}
                </td>
            </tr>
            <tr>
                <td class="document-meta-left"></td>
                <td class="document-meta-right">
                    <div class="meta-highlight">{{ __('Quotation Valid Date:') }} {{ $documentOrder['valid_until'] }}</div>
                </td>
            </tr>
        </table>

        <table class="quote-grid">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3%;">{{ __('No.') }}</th>
                    <th rowspan="2" style="width:14%;">{{ __('Item Name') }}</th>
                    <th rowspan="2" style="width:9%;">{{ __('Reference Pic') }}</th>
                    <th rowspan="2" style="width:5%;">{{ __('HS Code') }}</th>
                    <th rowspan="2" style="width:6%;">{{ __('Barcode') }}</th>
                    <th rowspan="2" style="width:6%;">{{ __('Material') }}</th>
                    <th rowspan="2" style="width:4%;">{{ __('Color') }}</th>
                    <th rowspan="2" style="width:4%;">{{ __('Size') }}</th>
                    <th colspan="2" style="width:10%;">{{ __('Packaging') }}</th>
                    <th rowspan="2" style="width:6%;">{{ __('Container') }}</th>
                    <th colspan="2" style="width:7%;">{{ __('Qty') }}</th>
                    <th rowspan="2" style="width:7%;">{{ __('Final Price') }}<br>{{ __('USD') }}</th>
                    <th rowspan="2" style="width:8%;">{{ __('Sub-total') }}<br>{{ __('USD') }}</th>
                    <th rowspan="2" style="width:6%;">{{ __('Lead Time') }}<br>{{ __('Days') }}</th>
                </tr>
                <tr class="subhead">
                    <th>{{ __('Qty / Carton') }}</th>
                    <th>{{ __('Carton Size') }}</th>
                    <th>{{ __('Pallets') }}</th>
                    <th>{{ __('PCS') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="align-center">{{ $item['line'] ?: $loop->iteration }}</td>
                        <td>{{ $item['item_name'] }}</td>
                        <td class="placeholder">{{ __('Image Ref') }}</td>
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
                        <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $item['unit_price'], 2) }}</td>
                        <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $item['line_total'], 2) }}</td>
                        <td class="align-center">{{ $documentOrder['production_days'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="placeholder">{{ __('No quotation items available.') }}</td>
                    </tr>
                @endforelse
                <tr class="accent-total">
                    <td colspan="13" class="align-right">{{ __('Total Quotation Value') }}</td>
                    <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $totals['unit_price'], 2) }}</td>
                    <td class="amount">{{ $totals['currency'] }} {{ number_format((float) $totals['grand_total'], 2) }}</td>
                    <td class="align-center">{{ $documentOrder['production_days'] }}</td>
                </tr>
            </tbody>
        </table>

        <div class="quote-summary-wrap">
            <table class="summary-table">
                <tr>
                    <td class="label-cell">{{ __('Quotation Subtotal') }}</td>
                    <td class="value-cell">{{ $totals['currency'] }} {{ number_format((float) $totals['subtotal'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label-cell">{{ __('Tax / Additional Charges') }}</td>
                    <td class="value-cell">{{ $totals['currency'] }} {{ number_format((float) $totals['tax_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td class="grand-cell">{{ __('Total Quotation Value') }}</td>
                    <td class="grand-cell value-cell">{{ $totals['currency'] }} {{ number_format((float) $totals['grand_total'], 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="payment-block">
            <div class="payment-section-title">{{ __('Payment Method ( For USD remittance )') }}</div>

            <table class="payment-table">
                <tr>
                    <th>{{ __('Beneficiary Name') }}</th>
                    <td>{{ $paymentDetails['beneficiary_name'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('Beneficiary Bank') }}</th>
                    <td>{{ $paymentDetails['beneficiary_bank'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('Beneficiary Account Numbers') }}</th>
                    <td>{{ $paymentDetails['account_number'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('Beneficiary Address') }}</th>
                    <td>{{ $paymentDetails['beneficiary_address'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('Bank Address') }}</th>
                    <td>{{ $paymentDetails['bank_address'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('SWIFT') }}</th>
                    <td>{{ $paymentDetails['swift_code'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('COUNTRY') }}</th>
                    <td>{{ $paymentDetails['country'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('PURPOSE OF PAYMENTS') }}</th>
                    <td class="bank-purpose">{{ $paymentDetails['payment_purpose'] }}</td>
                </tr>
            </table>

            <div class="remark-box">{{ __('REMARK: PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING. THANK YOU.') }}</div>
        </div>

        <table class="footer-table">
            <tr>
                <td>
                    <div class="footer-note"><strong>{{ __('Sales Representative:') }}</strong> {{ $salesRepresentative ?: __('Sales Team') }}</div>
                    <div class="footer-note"><strong>{{ __('Generated:') }}</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
                </td>
                <td style="width:36%; text-align:right;">
                    @if ($verificationQr)
                        <div class="verification-qr"><img src="{{ $verificationQr }}" alt="Verification QR"></div>
                    @endif
                    <div class="verification-caption">{{ __('Scan to verify') }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
