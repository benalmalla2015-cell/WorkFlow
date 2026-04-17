<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;

class WorkflowDocumentService
{
    public function __construct(private OrderPricingService $pricing)
    {
    }

    public function generateQuotation(Order $order): array
    {
        $order = $this->loadOrder($order);

        if (!$order->canGenerateCommercialDocuments()) {
            throw ValidationException::withMessages([
                'order' => 'لا يمكن توليد المستندات التجارية قبل اعتماد المدير للطلب.',
            ]);
        }

        $items = $this->resolveItems($order);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'يجب أن يحتوي الطلب على عنصر واحد على الأقل قبل توليد عرض السعر.',
            ]);
        }

        return $this->generatePdfDocument(
            $order,
            $items,
            'quotation',
            trim(config('workflow.quotations_root', 'quotations'), '/'),
            'quotation_generated',
            'documents.quotation-pdf',
            'quotation_path'
        );
    }

    public function generateInvoice(Order $order): array
    {
        $order = $this->loadOrder($order);

        if (!$order->canGenerateCommercialDocuments()) {
            throw ValidationException::withMessages([
                'order' => 'لا يمكن توليد المستندات التجارية قبل اعتماد المدير للطلب.',
            ]);
        }

        $items = $this->resolveItems($order);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'يجب أن يحتوي الطلب على عنصر واحد على الأقل قبل توليد الفاتورة.',
            ]);
        }

        return $this->generatePdfDocument(
            $order,
            $items,
            'invoice',
            trim(config('workflow.invoices_root', 'invoices'), '/'),
            'invoice_generated',
            'documents.invoice-pdf',
            'invoice_path'
        );
    }

    public function verificationUrl(Order $order): string
    {
        return route('orders.verify', ['orderNumber' => $order->order_number]);
    }

    private function loadOrder(Order $order): Order
    {
        return Order::withoutGlobalScopes()
            ->with(['customer', 'salesUser', 'factoryUser', 'items'])
            ->findOrFail($order->id);
    }

    private function resolveCustomerName(Order $order): string
    {
        return (string) ($order->resolvedCustomerName() ?: 'غير محدد');
    }

    private function resolveItems(Order $order): array
    {
        return collect($this->pricing->summarize($order)['line_items'] ?? [])
            ->map(function ($item) {
                return [
                    'item_name' => (string) ($item['item_name'] ?? ''),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'description' => (string) ($item['description'] ?? ''),
                    'sales_price' => round((float) ($item['sales_price'] ?? 0), 2),
                    'line_total' => round((float) ($item['sales_total'] ?? 0), 2),
                ];
            })
            ->filter(fn ($item) => $item['item_name'] !== '')
            ->values()
            ->all();
    }

    private function ensureDirectory(string $disk, string $root): void
    {
        if (!Storage::disk($disk)->exists($root)) {
            Storage::disk($disk)->makeDirectory($root);
        }
    }

    private function generatePdfDocument(
        Order $order,
        array $items,
        string $documentType,
        string $root,
        string $auditAction,
        string $view,
        string $pathField
    ): array {
        $disk = config('workflow.documents_disk', 'public');
        $filename = $documentType . '_' . $order->order_number . '_' . now()->format('Ymd_His') . '.pdf';
        $relativePath = $root . '/' . $filename;

        $this->ensureDirectory($disk, $root);

        $payload = [
            'order' => $order,
            'items' => $items,
            'company' => $this->companyProfile(),
            'generatedAt' => now(),
            'verificationUrl' => $this->verificationUrl($order),
            'totals' => $this->resolveTotals($order, $items),
        ];

        Storage::disk($disk)->put($relativePath, $this->renderPdf($view, $payload));

        $order->update([$pathField => $relativePath]);
        AuditLog::log($auditAction, $order);

        return [
            'filename' => $filename,
            'path' => $relativePath,
        ];
    }

    private function companyProfile(): array
    {
        return [
            'name' => (string) Setting::get('company_name', 'مؤسسة مدحت رشاد للحلول التقنية'),
            'address' => (string) Setting::get('company_address', ''),
            'phone' => (string) Setting::get('company_phone', ''),
            'email' => (string) Setting::get('company_email', ''),
            'attn' => (string) Setting::get('company_attn', ''),
            'beneficiary_name' => (string) Setting::get('beneficiary_name', ''),
            'beneficiary_bank' => (string) Setting::get('beneficiary_bank', ''),
            'account_number' => (string) Setting::get('account_number', ''),
            'swift_code' => (string) Setting::get('swift_code', ''),
            'bank_address' => (string) Setting::get('bank_address', ''),
            'beneficiary_address' => (string) Setting::get('beneficiary_address', ''),
        ];
    }

    private function resolveTotals(Order $order, array $items): array
    {
        $totalQuantity = array_sum(array_column($items, 'quantity'));
        $subtotal = round((float) array_sum(array_column($items, 'line_total')), 2);
        $unitPrice = $totalQuantity > 0 && $subtotal > 0 ? $subtotal / $totalQuantity : 0;
        $taxRate = (float) Setting::get('tax_rate', 0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $grandTotal = round($subtotal + $taxAmount, 2);

        return [
            'quantity' => $totalQuantity,
            'unit_price' => round($unitPrice, 2),
            'subtotal' => round($subtotal, 2),
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            'total' => $grandTotal,
            'grand_total' => $grandTotal,
            'currency' => (string) Setting::get('currency', 'USD'),
        ];
    }

    private function renderPdf(string $view, array $payload): string
    {
        $html = View::make($view, $payload)->render();

        if (class_exists(Mpdf::class)) {
            $tempDir = storage_path('app/mpdf-temp');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 8,
                'margin_right' => 8,
                'tempDir' => $tempDir,
                'default_font' => 'dejavusans',
            ]);
            $mpdf->SetDirectionality('rtl');
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        }

        return Pdf::setOption([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])->loadHTML($html)->setPaper('a4', 'portrait')->output();
    }
}
