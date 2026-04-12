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
            $filename = 'quotation_' . $order->order_number . '_' . date('Ymd') . '.xlsx';
            $dir = storage_path('app/public/quotations');
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $fullPath = $dir . '/' . $filename;

            $export = new QuotationExport($order);
            Excel::store($export, 'public/quotations/' . $filename);

            $order->update(['quotation_path' => 'quotations/' . $filename]);
            AuditLog::log('quotation_generated', $order);

            return response()->json([
                'message' => 'Quotation generated successfully',
                'filename' => $filename,
                'download_url' => '/api/orders/' . $order->id . '/download-quotation'
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
            // Generate QR code as base64 PNG
            $qrData = url('/api/orders/verify/' . $order->order_number);
            $qrPng  = QrCode::format('png')->size(120)->margin(1)->generate($qrData);
            $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);

            $invoiceData = $this->prepareInvoiceData($order, $qrBase64);

            $pdf = Pdf::loadView('invoices.template', $invoiceData);
            $pdf->setPaper('a4', 'portrait');

            $filename = 'invoice_' . $order->order_number . '_' . date('Ymd') . '.pdf';
            $dir = storage_path('app/public/invoices');
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            Storage::disk('public')->put('invoices/' . $filename, $pdf->output());

            $order->update(['invoice_path' => 'invoices/' . $filename]);
            AuditLog::log('invoice_generated', $order);

            return response()->json([
                'message' => 'Invoice generated successfully',
                'filename' => $filename,
                'download_url' => '/api/orders/' . $order->id . '/download-invoice'
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
        $path = storage_path('app/public/' . $order->quotation_path);
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->download($path);
    }

    public function downloadInvoice(Order $order)
    {
        if (!$order->invoice_path) {
            return response()->json(['message' => 'Invoice not generated yet'], 404);
        }
        $path = storage_path('app/public/' . $order->invoice_path);
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->download($path);
    }

    public function downloadAttachment(\App\Models\Attachment $attachment)
    {
        $path = storage_path('app/public/' . $attachment->path);
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->download($path, $attachment->original_name);
    }

    private function prepareInvoiceData($order, $qrBase64)
    {
        $qty  = max(1, (int)$order->quantity);
        $total = (float)($order->final_price ?? 0);
        $unit  = $qty > 0 ? $total / $qty : 0;

        return [
            'order'          => $order,
            'customer'       => $order->customer,
            'sales_user'     => $order->salesUser,
            'company'        => [
                'name'    => Setting::get('company_name',    'DAYANCO TRADING CO., LIMITED'),
                'address' => Setting::get('company_address', 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU 511455, P.R. CHINA'),
                'phone'   => Setting::get('company_phone',   '+86 188188 45411'),
                'email'   => Setting::get('company_email',   'team@dayancofficial.com'),
                'attn'    => Setting::get('company_attn',    'Mr. Abdulmalek'),
            ],
            'qr_code_base64' => $qrBase64,
            'invoice_number' => 'INV-' . $order->order_number,
            'invoice_date'   => now()->format('F j\s\t, Y'),
            'items'          => [[
                'name'        => $order->product_name,
                'description' => $order->specifications ?? '',
                'quantity'    => $qty,
                'unit_price'  => $unit,
                'total'       => $total,
            ]],
            'subtotal' => $total,
            'bank_details' => [
                'beneficiary_name'    => Setting::get('beneficiary_name',    'DAYANCO TRADING CO., LIMITED'),
                'beneficiary_bank'    => Setting::get('beneficiary_bank',    'ZHEJIANG CHOUZHOU COMMERCIAL BANK'),
                'account_number'      => Setting::get('account_number',      'NRA1564714201050006871'),
                'beneficiary_address' => Setting::get('beneficiary_address', '9F, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000 P.R. CHINA'),
                'bank_address'        => Setting::get('bank_address',        'YIWULEYUAN EAST, JIANGBEI RD, YIWU, ZHEJIANG CHINA'),
                'swift_code'          => Setting::get('swift_code',          'CZCBCNLX'),
                'country'             => 'China',
                'purpose'             => 'PURCHASE OF GOODS',
            ],
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
            'product'      => $order->product_name,
            'quantity'     => $order->quantity,
            'total_amount' => $order->final_price,
            'sales_person' => optional($order->salesUser)->name,
            'status'       => $order->status,
            'created_at'   => $order->created_at->format('Y-m-d H:i:s'),
            'verified'     => true,
        ]);
    }
}
