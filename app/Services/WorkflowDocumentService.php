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
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
            'documents.quotation-pdf-branded',
            'quotation_path',
            'landscape'
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
            'documents.invoice-pdf-branded',
            'invoice_path',
            'portrait'
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
                    'line' => (int) ($item['line'] ?? 0),
                    'item_name' => (string) ($item['item_name'] ?? ''),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'description' => (string) ($item['description'] ?? ''),
                    'supplier_name' => (string) ($item['supplier_name'] ?? ''),
                    'product_code' => (string) ($item['product_code'] ?? ''),
                    'unit_price' => round((float) ($item['sales_price'] ?? 0), 2),
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
        string $pathField,
        string $orientation = 'portrait'
    ): array {
        $disk = config('workflow.documents_disk', 'public');
        $filename = $documentType . '_' . $order->order_number . '_' . now()->format('Ymd_His') . '.pdf';
        $relativePath = $root . '/' . $filename;

        $this->ensureDirectory($disk, $root);

        $payload = $this->normalizeUtf8([
            'order' => $order,
            'documentType' => $documentType,
            'documentOrder' => [
                'order_number' => (string) $order->order_number,
                'customer_name' => $this->resolveCustomerName($order),
                'customer_address' => (string) ($order->customer?->address ?? ''),
                'customer_phone' => (string) ($order->customer?->phone ?? ''),
                'production_days' => (string) ($order->production_days ?: '—'),
                'product_name' => (string) ($order->product_name ?: collect($items)->pluck('item_name')->implode(' / ') ?: 'Quotation'),
                'file_number' => 'XXX-' . substr((string) $order->order_number, -9),
                'issue_date' => now()->format('Y-m-d'),
                'issue_date_long' => now()->format('F jS, Y'),
                'valid_until' => now()->copy()->addDays(21)->format('F jS, Y'),
            ],
            'items' => $items,
            'company' => $this->companyProfile(),
            'generatedAt' => now(),
            'verificationUrl' => $this->verificationUrl($order),
            'verificationQr' => $this->generateVerificationQr($this->verificationUrl($order)),
            'salesRepresentative' => (string) ($order->salesUser?->name ?? ''),
            'totals' => $this->resolveTotals($order, $items),
        ]);

        Storage::disk($disk)->put($relativePath, $this->renderPdf($view, $payload, $orientation));

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
            'country' => (string) Setting::get('company_country', 'China'),
            'payment_purpose' => (string) Setting::get('payment_purpose', 'PURCHASE OF GOODS'),
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

    private function generateVerificationQr(string $url): ?string
    {
        try {
            $qrPng = QrCode::format('png')
                ->size(130)
                ->margin(1)
                ->generate($url);

            return 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function renderPdf(string $view, array $payload, string $orientation = 'portrait'): string
    {
        @ini_set('memory_limit', '256M');

        $html = $this->sanitizeHtmlForPdf(View::make($view, $payload)->render());

        if (class_exists(Mpdf::class)) {
            try {
                return $this->renderWithMpdf($html, $orientation);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $this->renderWithDompdf($html, $orientation);
    }

    private function renderWithMpdf(string $html, string $orientation = 'portrait'): string
    {
        $fontDir = $this->ensureLocalDirectory(storage_path('fonts'));
        $tempDir = $this->ensureLocalDirectory(storage_path('app/mpdf-temp'));
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'landscape' ? 'A4-L' : 'A4',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 8,
            'margin_right' => 8,
            'tempDir' => $tempDir,
            'fontDir' => array_values(array_unique(array_merge($defaultConfig['fontDir'], [$fontDir]))),
            'default_font' => 'dejavusans',
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function renderWithDompdf(string $html, string $orientation = 'portrait'): string
    {
        $tempDir = $this->ensureLocalDirectory(storage_path('app/dompdf-temp'));

        return Pdf::setOption([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'tempDir' => $tempDir,
            'chroot' => base_path('public'),
        ])->loadHTML($html)->setPaper('a4', $orientation)->output();
    }

    private function ensureLocalDirectory(string $path): string
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        @chmod($path, 0775);

        return $path;
    }

    private function sanitizeHtmlForPdf(string $html): string
    {
        $html = $this->normalizeUtf8String($html);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? $html;
    }

    private function normalizeUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeUtf8($item), $value);
        }

        if (is_string($value)) {
            return $this->normalizeUtf8String($value);
        }

        return $value;
    }

    private function normalizeUtf8String(string $value): string
    {
        $value = str_replace("\0", '', $value);

        if ($value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        }

        foreach (['Windows-1256', 'ISO-8859-6', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);

            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $converted) ?? $converted;
            }
        }

        $stripped = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if (is_string($stripped) && $stripped !== '') {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $stripped) ?? $stripped;
        }

        return preg_replace('/[\x00-\x1F\x7F]+/', '', $value) ?? '';
    }
}
