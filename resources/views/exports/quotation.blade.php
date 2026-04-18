<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <!-- Header -->
    <tr>
        <td colspan="16" style="text-align: center; font-size: 18px; font-weight: bold; background-color: #E3F2FD;">
            {{ $company['name'] }} - QUOTATION
        </td>
    </tr>
    <tr>
        <td colspan="8">
            <strong>Quotation Number:</strong> {{ $quotation_number }}<br>
            <strong>Date:</strong> {{ $quotation_date }}<br>
            <strong>Page:</strong> 1
        </td>
        <td colspan="8">
            <strong>Customer:</strong> {{ $customer->full_name }}<br>
            <strong>Address:</strong> {{ $customer->address }}<br>
            <strong>Phone:</strong> {{ $customer->phone }}
        </td>
    </tr>
    
    <!-- Table Headers -->
    <tr style="background-color: #F5F5F5; font-weight: bold;">
        <td>No.</td>
        <td>Item Name</td>
        <td>Reference Picture</td>
        <td>HS CODE</td>
        <td>Barcode</td>
        <td>Material</td>
        <td>Color</td>
        <td>Size</td>
        <td>Packaging (Quantities/Carton)</td>
        <td>Carton Size</td>
        <td>Loading Container</td>
        <td>PALLETS</td>
        <td>PCS</td>
        <td>Final Price (USD)</td>
        <td>Sub-total (USD)</td>
        <td>Production Lead Time (DAYS)</td>
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
        <td colspan="14">TOTAL</td>
        <td>${{ number_format($total_amount, 2) }}</td>
        <td>{{ $items[0]['production_lead_time'] ?? 30 }}</td>
    </tr>
    
    <!-- Footer -->
    <tr>
        <td colspan="16" style="text-align: center; font-style: italic; border-top: 2px solid #333;">
            Sales Representative: {{ $sales_user->name }} | Generated on: {{ now()->format('Y-m-d H:i:s') }}
        </td>
    </tr>
</table>
