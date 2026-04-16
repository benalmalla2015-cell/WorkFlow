<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkflowDocumentService
{
    public function generateQuotation(Order $order): array
    {
        $order = $this->loadOrder($order);
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
        return $order->resolvedItems()
            ->map(function ($item) {
                return [
                    'item_name' => (string) ($item->item_name ?? ''),
                    'quantity' => max(1, (int) ($item->quantity ?? 1)),
                    'description' => (string) ($item->description ?? ''),
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

        $pdf = Pdf::setOption([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])->loadView($view, [
            'order' => $order,
            'items' => $items,
            'company' => $this->companyProfile(),
            'generatedAt' => now(),
            'verificationUrl' => $this->verificationUrl($order),
            'totals' => $this->resolveTotals($order, $items),
        ])->setPaper('a4', 'portrait');

        Storage::disk($disk)->put($relativePath, $pdf->output());

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
        $totalPrice = (float) ($order->final_price ?: $order->selling_price ?: 0);
        $unitPrice = $totalQuantity > 0 && $totalPrice > 0 ? $totalPrice / $totalQuantity : 0;

        return [
            'quantity' => $totalQuantity,
            'unit_price' => round($unitPrice, 2),
            'subtotal' => round($totalPrice, 2),
            'total' => round($totalPrice, 2),
            'factory_cost' => round((float) ($order->factory_cost ?: 0), 2),
            'profit_margin' => round((float) ($order->profit_margin_percentage ?: 0), 2),
        ];
    }
}
