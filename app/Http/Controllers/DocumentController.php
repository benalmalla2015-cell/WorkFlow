<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\WorkflowDocumentService;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function __construct(private WorkflowDocumentService $documents)
    {
    }

    public function generateQuotation(Order $order)
    {
        $this->authorizeOrderDocumentAccess($order);

        if (!$order->canGenerateCommercialDocuments()) {
            return response()->json([
                'message' => 'لا يمكن توليد عرض السعر قبل اعتماد المدير للطلب.',
            ], 422);
        }

        try {
            $document = $this->documents->generateQuotation($order);

            return response()->json([
                'message' => 'Quotation generated successfully',
                'filename' => $document['filename'],
                'download_url' => '/api/orders/' . $order->id . '/download-quotation'
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate quotation: ' . $e->getMessage()], 500);
        }
    }

    public function generateInvoice(Order $order)
    {
        $this->authorizeOrderDocumentAccess($order);

        if (!$order->canGenerateCommercialDocuments()) {
            return response()->json([
                'message' => 'لا يمكن توليد الفاتورة قبل اعتماد المدير للطلب.',
            ], 422);
        }

        try {
            $document = $this->documents->generateInvoice($order);

            return response()->json([
                'message' => 'Invoice generated successfully',
                'filename' => $document['filename'],
                'download_url' => '/api/orders/' . $order->id . '/download-invoice'
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate invoice: ' . $e->getMessage()], 500);
        }
    }

    public function downloadQuotation(Order $order)
    {
        $this->authorizeOrderDocumentAccess($order);

        $disk = config('workflow.documents_disk', 'public');

        if (!$order->quotation_path) {
            return $this->missingFileResponse('Quotation not generated yet');
        }

        if (!Storage::disk($disk)->exists($order->quotation_path)) {
            return $this->missingFileResponse('File not found');
        }

        return Storage::disk($disk)->download($order->quotation_path, basename($order->quotation_path));
    }

    public function downloadInvoice(Order $order)
    {
        $this->authorizeOrderDocumentAccess($order);

        $disk = config('workflow.documents_disk', 'public');

        if (!$order->invoice_path) {
            return $this->missingFileResponse('Invoice not generated yet');
        }

        if (!Storage::disk($disk)->exists($order->invoice_path)) {
            return $this->missingFileResponse('File not found');
        }

        return Storage::disk($disk)->download($order->invoice_path, basename($order->invoice_path));
    }

    public function downloadAttachment(\App\Models\Attachment $attachment)
    {
        if (!$attachment->canBeAccessedBy(request()->user())) {
            return $this->unauthorizedResponse();
        }

        $disk = config('workflow.uploads_disk', 'public');

        if (!Storage::disk($disk)->exists($attachment->path)) {
            return $this->missingFileResponse('File not found');
        }

        return Storage::disk($disk)->download($attachment->path, $attachment->original_name);
    }

    private function authorizeOrderDocumentAccess(Order $order): void
    {
        $user = request()->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isSales() && $order->sales_user_id === $user->id) {
            return;
        }

        abort(403, 'Unauthorized');
    }

    private function missingFileResponse($message)
    {
        if (request()->wantsJson()) {
            return response()->json(['message' => $message], 404);
        }

        return redirect()->back()->with('error', $message);
    }

    private function unauthorizedResponse()
    {
        if (request()->wantsJson()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return redirect()->back()->with('error', 'Unauthorized');
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
