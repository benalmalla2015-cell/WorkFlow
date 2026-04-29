<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ __('Invoice') }} {{ $invoice_number }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 9pt; color:#000; padding:15mm 12mm; }
.page { width:100%; }

/* Header */
.header-logo { text-align:right; margin-bottom:2px; }
.header-logo .brand { font-size:22pt; font-weight:bold; color:#1a5276; }
.header-logo .brand sup { font-size:10pt; }
.header-logo .tagline { font-size:8pt; color:#555; }
.header-addr { text-align:right; font-size:7.5pt; color:#333; margin-bottom:8px; }

/* TO line */
.to-line { margin:8px 0 4px 0; }
.to-line span { font-size:11pt; font-weight:bold; text-decoration:underline; }

/* Right-side invoice info */
.inv-info-table { width:100%; margin:6px 0; }
.inv-info-table td { font-size:8.5pt; padding:1px 0; }
.inv-info-table .label { font-weight:bold; }

/* Horizontal rule */
hr.thick { border:none; border-top:2px solid #000; margin:6px 0; }
hr.thin  { border:none; border-top:1px solid #aaa; margin:4px 0; }

/* INVOICE title */
.inv-title { text-align:center; font-size:16pt; font-weight:bold; letter-spacing:2px; margin:10px 0; }

/* Project line */
.project-line { font-size:8.5pt; margin:4px 0 10px 0; border-bottom:1px dashed #888; padding-bottom:4px; }

/* Items table */
table.items { width:100%; border-collapse:collapse; font-size:8.5pt; }
table.items th {
    background:#d5e8d4; border:1px solid #888; padding:5px 4px;
    text-align:left; font-size:8pt;
}
table.items td { border:1px solid #bbb; padding:5px 4px; vertical-align:top; }
table.items .amount { text-align:right; }
table.items .total-row td { background:#f5f5f5; font-weight:bold; }
table.items .grand-total td { background:#d5e8d4; font-weight:bold; font-size:9.5pt; }

/* Payment section */
.payment-title { font-size:9.5pt; font-weight:bold; text-decoration:underline; margin:10px 0 5px 0; }
table.bank { width:100%; border-collapse:collapse; font-size:8.5pt; margin-bottom:8px; }
table.bank td { border:1px solid #888; padding:4px 6px; }
table.bank .bank-label { background:#d5e8d4; font-weight:bold; width:38%; }

/* Footer */
.remark { font-size:7.5pt; color:#333; margin-top:6px; border-top:1px solid #ccc; padding-top:4px; }
.sales-footer { margin-top:10px; font-size:8.5pt; }
.qr-wrap { margin-top:10px; text-align:center; }
.qr-wrap img { width:90px; height:90px; }
.qr-wrap small { display:block; font-size:7pt; color:#555; }
</style>
</head>
<body>
<div class="page">

  {{-- ===== HEADER ===== --}}
  <table width="100%"><tr>
    <td valign="top" width="60%"></td>
    <td valign="top" width="40%" style="text-align:right;">
      <div class="brand">DAYANCO<sup>®</sup></div>
      <div style="font-size:8pt;color:#555;">| {{ __('Supply Chain Management') }} |</div>
    </td>
  </tr></table>

  <hr class="thick">

  {{-- TO line --}}
  <div class="to-line">
    <span>{{ __('TO') }} {{ __('Mr.') }} {{ optional($customer)->full_name ?? '?' }} &ndash; {{ __('Purchasing Manager') }}</span>
  </div>
  <hr class="thin">

  {{-- Invoice details right-aligned --}}
  <table class="inv-info-table">
    <tr>
      <td></td>
      <td style="text-align:right;">
        <span class="label">{{ __('Invoice Number:') }}</span> {{ $invoice_number }}<br>
        <span class="label">{{ __('Invoice Date:') }}</span> {{ $invoice_date }}<br>
        <span class="label">{{ __('Page:') }}</span> 1/{{ __('page') }}
      </td>
    </tr>
  </table>

  <div class="inv-title">{{ __('INVOICE') }}</div>
  <hr class="thick">

  <div class="project-line">
    <strong>{{ __('Project Name / ITEM NAME:') }}</strong> {{ $order->product_name }}
  </div>

  {{-- Items Table --}}
  <table class="items">
    <thead>
      <tr>
        <th style="width:12%">{{ __('Date') }}</th>
        <th style="width:18%">{{ __('Item') }}</th>
        <th>{{ __('Description') }}</th>
        <th style="width:18%;text-align:right;">{{ __('Amount (USD)') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      <tr>
        <td>{{ now()->format('M j\s\t') }}</td>
        <td>{{ $item['name'] }}</td>
        <td>
          {{ __('100% for') }} {{ $item['quantity'] }} {{ __('pcs') }}<br>
          – {{ __('Refer to quotation number') }} {{ $order->order_number }}<br>
          – {{ __('Specifications:') }} {{ $item['description'] ?: __('As approved quotation') }}<br>
          – {{ __('Production Lead Time: around') }} {{ $item['production_days'] }} {{ __('days') }}
        </td>
        <td class="amount">{{ number_format($item['total'], 2) }}</td>
      </tr>
      @endforeach

      <tr class="total-row">
        <td colspan="3" style="text-align:right;">{{ __('Sub-total Above USD') }}</td>
        <td class="amount">{{ number_format($subtotal, 2) }}</td>
      </tr>
      <tr class="total-row">
        <td colspan="3" style="text-align:right;">{{ __('70% Balance USD') }}</td>
        <td class="amount">{{ number_format($subtotal * 0.7, 2) }}</td>
      </tr>
      <tr>
        <td>{{ now()->format('M j\s\t') }}</td>
        <td>{{ __('Bank Fee') }}</td>
        <td>{{ __('Local Bank Charge of IMT') }}</td>
        <td class="amount">40.00</td>
      </tr>
      <tr class="grand-total">
        <td colspan="3" style="text-align:right;font-size:10pt;">{{ __('Total Amount due to This Invoice:') }}</td>
        <td class="amount" style="font-size:10pt;">USD&nbsp;{{ number_format($subtotal, 2) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Payment Section --}}
  <div class="payment-title">{{ __('Payment Method ( For USD remittance )') }}</div>
  <table class="bank">
    <tr>
      <td class="bank-label">{{ __('Beneficiary Name') }}</td>
      <td>{{ $bank_details['beneficiary_name'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('Beneficiary Bank') }}</td>
      <td>{{ $bank_details['beneficiary_bank'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('Beneficiary Account Numbers') }}</td>
      <td>{{ $bank_details['account_number'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('Beneficiary Address') }}</td>
      <td>{{ $bank_details['beneficiary_address'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('Bank Address') }}</td>
      <td>{{ $bank_details['bank_address'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('SWIFT') }}</td>
      <td>{{ $bank_details['swift_code'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('COUNTRY') }}</td>
      <td>{{ $bank_details['country'] }}</td>
    </tr>
    <tr>
      <td class="bank-label">{{ __('PURPOSE OF PAYMENTS') }}</td>
      <td>{{ $bank_details['purpose'] }}</td>
    </tr>
  </table>

  <div class="remark">
    <strong>{{ __('REMARK:') }}</strong> {{ __('PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING, THANK YOU.') }}
  </div>

  {{-- Footer: Sales person + QR --}}
  <table width="100%" style="margin-top:12px;">
    <tr>
      <td valign="bottom">
        <div class="sales-footer">
          <strong>{{ __('Sales Representative:') }}</strong> {{ optional($sales_user)->name }}<br>
          <small>{{ __('Generated:') }} {{ now()->format('Y-m-d H:i') }}</small>
        </div>
      </td>
      <td width="100" valign="top" style="text-align:center;">
        @if(!empty($qr_code_base64))
          <img src="{{ $qr_code_base64 }}" style="width:80px;height:80px;"><br>
          <small style="font-size:7pt;">{{ __('Scan to verify') }}</small>
        @endif
      </td>
    </tr>
  </table>

</div>
</body>
</html>
