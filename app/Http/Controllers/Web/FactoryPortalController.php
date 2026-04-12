<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactoryPortalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['attachments.uploadedBy', 'salesUser', 'factoryUser']);

        if (!$user->isAdmin()) {
            $query->where(function ($builder) use ($user) {
                $builder->where('status', 'factory_pricing')
                    ->orWhere('factory_user_id', $user->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        $orders = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('factory.orders.index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorizeFactoryAccess($request, $order);
        $order->load(['attachments.uploadedBy', 'salesUser', 'factoryUser']);

        return view('factory.orders.form', [
            'order' => $order,
            'defaultMargin' => (float) Setting::get('default_profit_margin', 20),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeFactoryAccess($request, $order);

        if (!$order->canBeEditedBy($request->user())) {
            return back()->with('error', 'لا يمكن تحديث هذا الطلب حالياً.');
        }

        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'product_code' => ['required', 'string', 'max:100'],
            'factory_cost' => ['required', 'numeric', 'min:0'],
            'production_days' => ['required', 'integer', 'min:1'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        DB::transaction(function () use ($request, $order, $validated) {
            $oldValues = $order->toArray();
            $defaultMargin = (float) Setting::get('default_profit_margin', 20);
            $sellingPrice = (float) $validated['factory_cost'] * (1 + ($defaultMargin / 100));

            $order->update([
                'supplier_name' => $validated['supplier_name'],
                'product_code' => $validated['product_code'],
                'factory_cost' => $validated['factory_cost'],
                'production_days' => $validated['production_days'],
                'factory_user_id' => $request->user()->id,
                'selling_price' => $sellingPrice,
                'profit_margin_percentage' => $defaultMargin,
                'status' => 'manager_review',
            ]);

            $this->storeAttachments($request, $order);
            AuditLog::log('order_updated', $order, $oldValues, $order->toArray());
        });

        return redirect()->route('factory.orders.index')->with('success', 'تم إرسال التسعير إلى المدير للمراجعة.');
    }

    private function authorizeFactoryAccess(Request $request, Order $order): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_if($order->factory_user_id !== $request->user()->id && $order->status !== 'factory_pricing', 403);
    }

    private function storeAttachments(Request $request, Order $order): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $disk = config('workflow.uploads_disk', 'public');
            $folder = config('workflow.factory_upload_root', 'factory_uploads');
            $path = $file->store($folder, $disk);

            Attachment::create([
                'order_id' => $order->id,
                'uploaded_by' => $request->user()->id,
                'file_name' => $file->hashName(),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'path' => $path,
                'type' => 'factory_upload',
            ]);
        }
    }
}
