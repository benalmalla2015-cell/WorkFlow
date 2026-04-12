<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .invoice-info, .customer-info {
            width: 48%;
        }
        .invoice-info h3, .customer-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #333;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .items-table .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .bank-details {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .bank-details h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-style: italic;
            color: #666;
        }
        .qr-code {
            text-align: center;
            margin-top: 20px;
        }
        .qr-code img {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">{{ $company['name'] }}</div>
            <div>SALES INVOICE</div>
        </div>

        <!-- Invoice and Customer Details -->
        <div class="invoice-details">
            <div class="invoice-info">
                <h3>Invoice Details</h3>
                <p><strong>Invoice Number:</strong> {{ $invoice_number }}</p>
                <p><strong>Invoice Date:</strong> {{ $invoice_date }}</p>
                <p><strong>Page:</strong> 1</p>
            </div>
            <div class="customer-info">
                <h3>Customer Information</h3>
                <p><strong>Name:</strong> {{ $customer->full_name }}</p>
                <p><strong>Address:</strong> {{ $customer->address }}</p>
                <p><strong>Phone:</strong> {{ $customer->phone }}</p>
                @if($customer->email)
                <p><strong>Email:</strong> {{ $customer->email }}</p>
                @endif
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price (USD)</th>
                    <th>Amount (USD)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['description'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>${{ number_format($item['unit_price'], 2) }}</td>
                    <td>${{ number_format($item['total'], 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5"><strong>Sub-total</strong></td>
                    <td><strong>${{ number_format($subtotal, 2) }}</strong></td>
                </tr>
                <tr class="total-row">
                    <td colspan="5"><strong>Balance</strong></td>
                    <td><strong>${{ number_format($subtotal, 2) }}</strong></td>
                </tr>
                <tr class="total-row">
                    <td colspan="5"><strong>Bank Fee</strong></td>
                    <td>$0.00</td>
                </tr>
                <tr class="total-row">
                    <td colspan="5"><strong>Local Bank Charge of IMT</strong></td>
                    <td>$0.00</td>
                </tr>
                <tr class="total-row" style="background-color: #e8f5e8; font-size: 16px;">
                    <td colspan="5"><strong>Total Amount due</strong></td>
                    <td><strong>${{ number_format($subtotal, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Payment Details -->
        <div class="bank-details">
            <h3>Payment Method</h3>
            <p><strong>Beneficiary Name:</strong> {{ $bank_details['beneficiary_name'] }}</p>
            <p><strong>Beneficiary Bank:</strong> {{ $bank_details['beneficiary_bank'] }}</p>
            <p><strong>Beneficiary Account Numbers:</strong> {{ $bank_details['account_number'] }}</p>
            <p><strong>Beneficiary Address:</strong> {{ $company['address'] }}</p>
            <p><strong>Bank Address:</strong> {{ $bank_details['bank_address'] }}</p>
            <p><strong>SWIFT:</strong> {{ $bank_details['swift_code'] }}</p>
            <p><strong>Country:</strong> China</p>
            <p><strong>Purpose of Payments:</strong> Payment for Invoice {{ $invoice_number }}</p>
        </div>

        <!-- QR Code -->
        <div class="qr-code">
            <img src="{{ $qr_code_url }}" alt="QR Code for Verification">
            <p><small>Scan QR code to verify invoice details</small></p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>REMARK:</strong> PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING, THANK YOU.</p>
            <p>Sales Representative: {{ $sales_user->name }} | Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
