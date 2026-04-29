<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <!-- Header -->
    <tr>
        <td colspan="16" style="text-align: center; font-size: 18px; font-weight: bold; background-color: #E3F2FD;">
            {{ $company['name'] }} - {{ __('QUOTATION') }}
        </td>
    </tr>
    <tr>
        <td colspan="8">
            <strong>{{ __('Quotation Number:') }}</strong> {{ $quotation_number }}<br>
            <strong>{{ __('Date:') }}</strong> {{ $quotation_date }}<br>
            <strong>{{ __('Page:') }}</strong> 1
        </td>
        <td colspan="8">
            <strong>{{ __('Customer:') }}</strong> {{ $customer->full_name }}<br>
            <strong>{{ __('Address:') }}</strong> {{ $customer->address }}<br>
            <strong>{{ __('Phone:') }}</strong> {{ $customer->phone }}
        </td>
    </tr>
    
    <!-- Table Headers -->
    <tr style="background-color: #F5F5F5; font-weight: bold;">
        <td>{{ __('No.') }}</td>
        <td>{{ __('Item Name') }}</td>
        <td>{{ __('Reference Picture') }}</td>
        <td>{{ __('HS CODE') }}</td>
        <td>{{ __('Barcode') }}</td>
        <td>{{ __('Material') }}</td>
        <td>{{ __('Color') }}</td>
        <td>{{ __('Size') }}</td>
        <td>{{ __('Packaging (Quantities/Carton)') }}</td>
        <td>{{ __('Carton Size') }}</td>
        <td>{{ __('Loading Container') }}</td>
        <td>{{ __('PALLETS') }}</td>
        <td>{{ __('PCS') }}</td>
        <td>{{ __('Final Price (USD)') }}</td>
        <td>{{ __('Sub-total (USD)') }}</td>
        <td>{{ __('Production Lead Time (DAYS)') }}</td>
    </tr>
    
    <!-- Items -->
    @foreach($items as $item)
    <tr>
        <td>{{ $item['no'] }}</td>
        <td>{{ $item['item_name'] }}</td>
        <td>{{ $item['reference_picture'] }}</td>
        <td>{{ $item['hs_code'] }}</td>
        <td>{{ $item['barcode'] }}</td>
        <td>{{ $item['material'] }}</td>
        <td>{{ $item['color'] }}</td>
        <td>{{ $item['size'] }}</td>
        <td>{{ $item['packaging_quantities'] }}</td>
        <td>{{ $item['carton_size'] }}</td>
        <td>{{ $item['loading_container'] }}</td>
        <td>{{ $item['pallets'] }}</td>
        <td>{{ $item['pcs'] }}</td>
        <td>${{ number_format($item['unit_cost'], 2) }}</td>
        <td>${{ number_format($item['subtotal_cost'], 2) }}</td>
        <td>{{ $item['production_lead_time'] }}</td>
    </tr>
    @endforeach
    
    <!-- Total -->
    <tr style="background-color: #E8F5E8; font-weight: bold;">
        <td colspan="14">{{ __('TOTAL') }}</td>
        <td>${{ number_format($total_amount, 2) }}</td>
        <td>{{ $items[0]['production_lead_time'] ?? 30 }}</td>
    </tr>
    
    <!-- Footer -->
    <tr>
        <td colspan="16" style="text-align: center; font-style: italic; border-top: 2px solid #333;">
            {{ __('Sales Representative:') }} {{ $sales_user->name }} | {{ __('Generated on:') }} {{ now()->format('Y-m-d H:i:s') }}
        </td>
    </tr>
</table>
