<?php

namespace App\Services;

use App\Exports\QuotationExport;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class WorkflowDocumentService
{
    public function generateQuotation(Order $order): array
    {
        $order = Order::withoutGlobalScopes()->with(['customer', 'salesUser'])->findOrFail($order->id);

        if (!$order->isApproved() && !$order->isCustomerApproved() && !$order->isPaymentConfirmed() && !$order->isCompleted()) {
            throw ValidationException::withMessages([
                'order' => 'Order must be approved to generate quotation',
            ]);
        }

        $disk = config('workflow.documents_disk', 'public');
        $root = trim(config('workflow.quotations_root', 'quotations'), '/');
        $filename = 'quotation_' . $order->order_number . '_' . now()->format('Ymd') . '.xlsx';

        Excel::store(new QuotationExport($order), $root . '/' . $filename, $disk);

        $order->update(['quotation_path' => $root . '/' . $filename]);
        AuditLog::log('quotation_generated', $order);

        return [
            'filename' => $filename,
            'path' => $root . '/' . $filename,
        ];
    }

    public function generateInvoice(Order $order): array
    {
        $order = Order::withoutGlobalScopes()->with(['customer', 'salesUser'])->findOrFail($order->id);

        if (!$order->payment_confirmed && !$order->isCompleted()) {
            throw ValidationException::withMessages([
                'order' => 'Order payment must be confirmed before generating invoice',
            ]);
        }

        $disk = config('workflow.documents_disk', 'public');
        $root = trim(config('workflow.invoices_root', 'invoices'), '/');
        $qrPng = QrCode::format('png')->size(120)->margin(1)->generate($this->verificationUrl($order));
        $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);
        $filename = 'invoice_' . $order->order_number . '_' . now()->format('Ymd') . '.pdf';
        $pdf = Pdf::loadView('invoices.template', $this->prepareInvoiceData($order, $qrBase64));

        $pdf->setPaper('a4', 'portrait');
        Storage::disk($disk)->put($root . '/' . $filename, $pdf->output());

        $order->update(['invoice_path' => $root . '/' . $filename]);
        AuditLog::log('invoice_generated', $order);

        return [
            'filename' => $filename,
            'path' => $root . '/' . $filename,
        ];
    }

    public function verificationUrl(Order $order): string
    {
        return route('orders.verify', ['orderNumber' => $order->order_number]);
    }

    public function prepareInvoiceData(Order $order, string $qrBase64): array
    {
        $qty = max(1, (int) $order->quantity);
        $total = (float) ($order->final_price ?? 0);
        $unit = $qty > 0 ? $total / $qty : 0;
        $days = max(1, (int) ($order->production_days ?? 30));

        return [
            'order' => $order,
            'customer' => $order->customer,
            'sales_user' => $order->salesUser,
            'company' => [
                'name' => Setting::get('company_name', 'DAYANCO TRADING CO., LIMITED'),
                'address' => Setting::get('company_address', 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU 511455, P.R. CHINA'),
                'phone' => Setting::get('company_phone', '+86 188188 45411'),
                'email' => Setting::get('company_email', 'team@dayancofficial.com'),
                'attn' => Setting::get('company_attn', 'Mr. Abdulmalek'),
            ],
            'qr_code_base64' => $qrBase64,
            'invoice_number' => 'INV-' . $order->order_number,
            'invoice_date' => now()->format('F j\s\t, Y'),
            'items' => [[
                'name' => $order->product_name,
                'description' => $order->specifications ?? '',
                'quantity' => $qty,
                'production_days' => $days,
                'unit_price' => $unit,
                'total' => $total,
            ]],
            'subtotal' => $total,
            'bank_details' => [
                'beneficiary_name' => Setting::get('beneficiary_name', 'DAYANCO TRADING CO., LIMITED'),
                'beneficiary_bank' => Setting::get('beneficiary_bank', 'ZHEJIANG CHOUZHOU COMMERCIAL BANK'),
                'account_number' => Setting::get('account_number', 'NRA1564714201050006871'),
                'beneficiary_address' => Setting::get('beneficiary_address', '9F, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000 P.R. CHINA'),
                'bank_address' => Setting::get('bank_address', 'YIWULEYUAN EAST, JIANGBEI RD, YIWU, ZHEJIANG CHINA'),
                'swift_code' => Setting::get('swift_code', 'CZCBCNLX'),
                'country' => 'China',
                'purpose' => 'PURCHASE OF GOODS',
            ],
        ];
    }
}
