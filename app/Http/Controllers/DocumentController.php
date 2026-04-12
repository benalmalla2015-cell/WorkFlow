<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Exports\QuotationExport;

class DocumentController extends Controller
{
    public function generateQuotation(Order $order)
    {
        if (!$order->isApproved() && !$order->isCustomerApproved()) {
            return response()->json(['message' => 'Order must be approved to generate quotation'], 400);
        }

        try {
            $filename = 'quotation_' . $order->order_number . '_' . date('Y-m-d') . '.xlsx';
            
            $export = new QuotationExport($order);
            $filePath = 'quotations/' . $filename;
            
            // Store to S3
            Excel::store($export, $filePath, 's3');
            
            // Update order with quotation path
            $order->update(['quotation_path' => $filePath]);
            
            AuditLog::log('quotation_generated', $order);

            return response()->json([
                'message' => 'Quotation generated successfully',
                'download_url' => Storage::disk('s3')->url($filePath),
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate quotation: ' . $e->getMessage()], 500);
        }
    }

    public function generateInvoice(Order $order)
    {
        if (!$order->isCustomerApproved()) {
            return response()->json(['message' => 'Order must be approved by customer to generate invoice'], 400);
        }

        try {
            // Generate QR code
            $qrCodeData = $this->generateQRCodeData($order);
            $qrCodePath = 'qrcodes/invoice_' . $order->order_number . '.png';
            
            $qrCode = QrCode::format('png')->size(150)->generate($qrCodeData);
            Storage::disk('s3')->put($qrCodePath, $qrCode);

            // Prepare invoice data
            $invoiceData = $this->prepareInvoiceData($order, $qrCodePath);

            // Generate PDF
            $pdf = PDF::loadView('invoices.template', $invoiceData);
            $pdf->setPaper('a4', 'portrait');

            $filename = 'invoice_' . $order->order_number . '_' . date('Y-m-d') . '.pdf';
            $filePath = 'invoices/' . $filename;
            
            // Store to S3
            Storage::disk('s3')->put($filePath, $pdf->output());

            // Update order with invoice path
            $order->update(['invoice_path' => $filePath]);

            AuditLog::log('invoice_generated', $order);

            return response()->json([
                'message' => 'Invoice generated successfully',
                'download_url' => Storage::disk('s3')->url($filePath),
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate invoice: ' . $e->getMessage()], 500);
        }
    }

    public function downloadQuotation(Order $order)
    {
        if (!$order->quotation_path) {
            return response()->json(['message' => 'Quotation not generated yet'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($order->quotation_path, now()->addHours(1));

        return response()->json(['download_url' => $url]);
    }

    public function downloadInvoice(Order $order)
    {
        if (!$order->invoice_path) {
            return response()->json(['message' => 'Invoice not generated yet'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($order->invoice_path, now()->addHours(1));

        return response()->json(['download_url' => $url]);
    }

    private function generateQRCodeData($order)
    {
        $data = [
            'order_number' => $order->order_number,
            'customer' => $order->customer->full_name,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total_amount' => $order->final_price,
            'sales_person' => $order->salesUser->name,
            'date' => $order->created_at->format('Y-m-d'),
            'verification_url' => route('orders.verify', $order->order_number)
        ];

        return json_encode($data);
    }

    private function prepareInvoiceData($order, $qrCodePath)
    {
        return [
            'order' => $order,
            'customer' => $order->customer,
            'sales_user' => $order->salesUser,
            'company' => [
                'name' => Setting::get('company_name', 'DAYANCO'),
                'address' => Setting::get('company_address', 'Company Address'),
                'phone' => Setting::get('company_phone', '+1234567890'),
            ],
            'qr_code_url' => Storage::disk('s3')->url($qrCodePath),
            'invoice_number' => 'INV-' . $order->order_number,
            'invoice_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'name' => $order->product_name,
                    'description' => $order->specifications,
                    'quantity' => $order->quantity,
                    'unit_price' => $order->final_price / $order->quantity,
                    'total' => $order->final_price
                ]
            ],
            'subtotal' => $order->final_price,
            'bank_details' => [
                'beneficiary_name' => Setting::get('beneficiary_name', 'DAYANCO'),
                'beneficiary_bank' => Setting::get('beneficiary_bank', 'Bank Name'),
                'account_number' => Setting::get('account_number', '123456789'),
                'swift_code' => Setting::get('swift_code', 'SWIFT123'),
                'bank_address' => Setting::get('bank_address', 'Bank Address'),
            ]
        ];
    }

    public function verifyOrder($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'customer' => $order->customer->full_name,
            'product' => $order->product_name,
            'quantity' => $order->quantity,
            'total_amount' => $order->final_price,
            'sales_person' => $order->salesUser->name,
            'status' => $order->status,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'verified' => true
        ]);
    }
}
