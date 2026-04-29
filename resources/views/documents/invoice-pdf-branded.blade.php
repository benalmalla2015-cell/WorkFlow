@php
    $cssPath = base_path('public/css/pdf-documents.css');
    $logoPath = base_path('public/images/dayanco-logo.svg');
    $pdfCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';
    $logoSvg = file_exists($logoPath) ? file_get_contents($logoPath) : '';
    $logoMarkup = $logoSvg !== '' ? $logoSvg : '<div class="brand-fallback">DAYANCO</div>';
    $bankFee = 40.00;
    $productsTotal = round((float) $totals['subtotal'], 2);
    $invoiceTotalDue = round($productsTotal + $bankFee, 2);
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
    <title>{{ __('Invoice') }} {{ $documentOrder['order_number'] }}</title>
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

        <div class="recipient-row"><span class="label">{{ __('TO') }}</span> {{ __('Mr.') }} {{ $documentOrder['customer_name'] }} - {{ __('Purchasing Manager') }}</div>

        <table class="document-meta-table">
            <tr>
                <td class="document-meta-left"></td>
                <td class="document-meta-right">
                    <span class="meta-label">{{ __('Invoice Number:') }}</span> {{ $documentOrder['order_number'] }}<br>
                    <span class="meta-label">{{ __('Invoice Date:') }}</span> {{ $documentOrder['issue_date_long'] }}<br>
                    <span class="meta-label">{{ __('Page:') }}</span> 1/{{ __('page') }}
                </td>
            </tr>
        </table>

        <div class="document-title">{{ __('INVOICE') }}</div>
        <hr class="rule-strong">

        <div class="project-line"><strong>{{ __('Project Name:') }}</strong> {{ $documentOrder['product_name'] }}</div>

        <table class="invoice-items">
            <thead>
                <tr>
                    <th style="width:14%;">{{ __('Date') }}</th>
                    <th style="width:20%;">{{ __('Item') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th style="width:18%;">{{ __('Amount') }} ({{ $totals['currency'] }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $generatedAt->format('M d') }}</td>
                        <td>{{ $item['item_name'] }}</td>
                        <td>
                            {{ __('100% for') }} {{ number_format((float) $item['quantity']) }} {{ __('pcs') }}<br>
                            - {{ __('Refer to quotation number') }} {{ $documentOrder['order_number'] }}<br>
                            - {{ __('Specifications:') }} {{ $item['description'] ?: __('As approved quotation') }}<br>
                            - {{ __('Production Lead Time: around') }} {{ $documentOrder['production_days'] }} {{ __('days') }}
                        </td>
                        <td class="amount">{{ number_format((float) $item['line_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="placeholder">{{ __('No invoice items available.') }}</td>
                    </tr>
                @endforelse
                <tr class="soft-total">
                    <td colspan="3" class="align-right">{{ __('Products Total Amount:') }}</td>
                    <td class="amount">{{ number_format($productsTotal, 2) }}</td>
                </tr>
                <tr>
                    <td>{{ $generatedAt->format('M d') }}</td>
                    <td>{{ __('Local Bank Fee') }}</td>
                    <td>{{ __('Local Bank Charge of IMT') }}</td>
                    <td class="amount">{{ number_format($bankFee, 2) }}</td>
                </tr>
                <tr class="grand-row">
                    <td colspan="3" class="align-right">{{ __('Final Total Amount:') }}</td>
                    <td class="amount"><span class="amount-chip">{{ $totals['currency'] }}{{ number_format($invoiceTotalDue, 2) }}</span></td>
                </tr>
            </tbody>
        </table>

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
