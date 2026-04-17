<?php

namespace App\Services;

use App\Models\AdjustmentLog;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderChangeDecisionNotification;
use App\Notifications\OrderChangeRequestedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderChangeService
{
    public function __construct(
        private UserNotificationService $notifications,
        private OrderPricingService $pricing
    )
    {
    }

    public function submitSalesChangeRequest(Order $order, User $requester, array $payload, array $stagedAttachments = []): void
    {
        $snapshot = $this->snapshotOrder($order->fresh(['customer', 'items', 'attachments']));
        $proposed = [
            'customer' => $payload['customer'],
            'order' => $payload['order'],
            'items' => $payload['items'],
            'attachments' => $stagedAttachments,
        ];

        $pendingChanges = [
            'type' => 'sales_edit',
            'requested_by' => [
                'id' => $requester->id,
                'name' => $requester->name,
                'role' => $requester->role,
            ],
            'requested_at' => now()->toIso8601String(),
            'previous_status' => $order->status,
            'target_status' => $order->status,
            'changed_fields' => $this->detectChangedFields($snapshot, $proposed),
            'current' => $snapshot,
            'proposed' => $proposed,
        ];

        DB::transaction(function () use ($order, $requester, $pendingChanges) {
            $oldValues = $order->toArray();
            $adjustmentLog = $this->createAdjustmentLog($order, $requester, $pendingChanges);

            $order->update([
                'status' => 'pending_approval',
                'pending_changes' => array_merge($pendingChanges, ['adjustment_log_id' => $adjustmentLog->id]),
                'pending_change_requested_by' => $requester->id,
                'pending_change_requested_at' => now(),
                'pending_change_original_status' => $pendingChanges['previous_status'],
            ]);

            AuditLog::log(
                'order_change_requested',
                $order,
                $oldValues,
                [
                    'status' => 'pending_approval',
                    'pending_changes' => $pendingChanges,
                ],
                $pendingChanges['changed_fields']
            );

            $this->notifyAdmins($order->fresh(), $requester, $pendingChanges);
        });
    }

    public function submitFactoryChangeRequest(Order $order, User $requester, array $payload, array $stagedAttachments = []): void
    {
        $snapshot = $this->snapshotOrder($order->fresh(['customer', 'items', 'attachments']));
        $proposed = [
            'order' => [
                'production_days' => $payload['production_days'],
                'factory_user_id' => $requester->id,
            ],
            'items' => $payload['items'],
            'attachments' => $stagedAttachments,
        ];

        $targetStatus = $order->status === 'factory_pricing' ? 'manager_review' : $order->status;

        $pendingChanges = [
            'type' => 'factory_edit',
            'requested_by' => [
                'id' => $requester->id,
                'name' => $requester->name,
                'role' => $requester->role,
            ],
            'requested_at' => now()->toIso8601String(),
            'previous_status' => $order->status,
            'target_status' => $targetStatus,
            'changed_fields' => $this->detectChangedFields($snapshot, $proposed),
            'current' => $snapshot,
            'proposed' => $proposed,
        ];

        DB::transaction(function () use ($order, $requester, $pendingChanges) {
            $oldValues = $order->toArray();
            $adjustmentLog = $this->createAdjustmentLog($order, $requester, $pendingChanges);

            $order->update([
                'status' => 'pending_approval',
                'pending_changes' => array_merge($pendingChanges, ['adjustment_log_id' => $adjustmentLog->id]),
                'pending_change_requested_by' => $requester->id,
                'pending_change_requested_at' => now(),
                'pending_change_original_status' => $pendingChanges['previous_status'],
            ]);

            AuditLog::log(
                'order_change_requested',
                $order,
                $oldValues,
                [
                    'status' => 'pending_approval',
                    'pending_changes' => $pendingChanges,
                ],
                $pendingChanges['changed_fields']
            );

            $this->notifyAdmins($order->fresh(), $requester, $pendingChanges);
        });
    }

    public function approvePendingChange(Order $order, User $admin): void
    {
        $order = Order::withoutGlobalScopes()
            ->with(['customer', 'items', 'attachments', 'salesUser', 'factoryUser', 'pendingAdjustmentLog.requester'])
            ->findOrFail($order->id);

        [$adjustmentLog, $pendingChanges] = $this->resolvePendingContext($order);
        $oldSnapshot = $this->snapshotOrder($order);

        DB::transaction(function () use ($order, $admin, $adjustmentLog, $pendingChanges, $oldSnapshot) {
            $type = $adjustmentLog?->type ?? ($pendingChanges['type'] ?? null);
            $proposed = $adjustmentLog?->proposed_payload ?? ($pendingChanges['proposed'] ?? []);
            $requesterId = (int) ($adjustmentLog?->requester_id ?: data_get($pendingChanges, 'requested_by.id'));
            $resolvedStatus = $adjustmentLog?->target_status ?? ($pendingChanges['target_status'] ?? ($order->pending_change_original_status ?: 'draft'));

            if ($type === 'sales_edit') {
                $customerData = $proposed['customer'] ?? [];
                if ($order->customer && $customerData !== []) {
                    $order->customer->update($customerData);
                }

                $orderData = Arr::only($proposed['order'] ?? [], [
                    'customer_name',
                    'product_name',
                    'quantity',
                    'specifications',
                    'customer_notes',
                ]);

                if ($orderData !== []) {
                    $order->fill($orderData);
                }

                $order->items()->delete();
                $order->items()->createMany($proposed['items'] ?? []);
                $order->supplier_name = null;
                $order->product_code = null;
                $order->factory_cost = null;
                $order->selling_price = null;
                $order->final_price = null;
                $order->total_price = null;
                $order->net_profit = null;
                $order->manager_approval = false;
                $order->quotation_path = null;
                $order->invoice_path = null;
                $resolvedStatus = $order->factory_user_id ? 'factory_pricing' : 'sent_to_factory';
            }

            if ($type === 'factory_edit') {
                $factoryData = Arr::only($proposed['order'] ?? [], [
                    'production_days',
                    'factory_user_id',
                ]);

                $items = $order->items()->get()->keyBy('id');
                foreach ($proposed['items'] ?? [] as $itemData) {
                    $item = $items->get((int) ($itemData['id'] ?? 0));

                    if (!$item) {
                        continue;
                    }

                    $item->update([
                        'supplier_name' => $itemData['supplier_name'] ?? null,
                        'product_code' => $itemData['product_code'] ?? null,
                        'unit_cost' => $itemData['unit_cost'] ?? null,
                    ]);
                }

                $order->fill($factoryData);
                $order->manager_approval = false;
                $this->pricing->syncOrderPricing($order, (float) Setting::get('default_profit_margin', 20));
            }

            $order->status = $resolvedStatus;
            $order->pending_changes = null;
            $order->pending_change_requested_by = null;
            $order->pending_change_requested_at = null;
            $order->pending_change_original_status = null;
            $this->syncFinancialValues($order);
            $order->save();

            $this->persistApprovedAttachments($order, $proposed['attachments'] ?? [], $requesterId);

            if ($adjustmentLog) {
                $adjustmentLog->update([
                    'status' => 'approved',
                    'reviewer_id' => $admin->id,
                    'review_notes' => null,
                    'reviewed_at' => now(),
                ]);
            }

            $newSnapshot = $this->snapshotOrder($order->fresh(['customer', 'items', 'attachments']));

            AuditLog::log(
                'pending_change_approved',
                $order,
                $oldSnapshot,
                $newSnapshot,
                array_values(array_unique(array_merge(
                    $pendingChanges['changed_fields'] ?? [],
                    ['order.total_price', 'order.net_profit', 'order.final_price']
                )))
            );

            $requester = $adjustmentLog?->requester ?: User::find($requesterId);
            if ($requester) {
                $this->notifications->send($requester, new OrderChangeDecisionNotification($order->fresh(), 'approved'));
            }
        });
    }

    public function rejectPendingChange(Order $order, User $admin, ?string $reason = null): void
    {
        $order = Order::withoutGlobalScopes()->with('pendingAdjustmentLog.requester')->findOrFail($order->id);
        [$adjustmentLog, $pendingChanges] = $this->resolvePendingContext($order);
        $requesterId = (int) ($adjustmentLog?->requester_id ?: data_get($pendingChanges, 'requested_by.id'));

        DB::transaction(function () use ($order, $admin, $adjustmentLog, $pendingChanges, $requesterId, $reason) {
            $oldValues = $order->toArray();
            $proposed = $adjustmentLog?->proposed_payload ?? ($pendingChanges['proposed'] ?? []);
            $this->deleteStagedAttachments($proposed['attachments'] ?? []);

            $order->update([
                'status' => $adjustmentLog?->previous_status ?: ($order->pending_change_original_status ?: ($pendingChanges['previous_status'] ?? 'draft')),
                'pending_changes' => null,
                'pending_change_requested_by' => null,
                'pending_change_requested_at' => null,
                'pending_change_original_status' => null,
            ]);

            if ($adjustmentLog) {
                $adjustmentLog->update([
                    'status' => 'rejected',
                    'reviewer_id' => $admin->id,
                    'review_notes' => $reason,
                    'reviewed_at' => now(),
                ]);
            }

            AuditLog::log(
                'pending_change_rejected',
                $order,
                $oldValues,
                [
                    'status' => $order->status,
                    'reason' => $reason,
                ],
                $pendingChanges['changed_fields'] ?? []
            );

            $requester = $adjustmentLog?->requester ?: User::find($requesterId);
            if ($requester) {
                $this->notifications->send($requester, new OrderChangeDecisionNotification($order->fresh(), 'rejected', $reason));
            }
        });
    }

    public function requestPendingChangeRevision(Order $order, User $admin, ?string $reason = null): void
    {
        $order = Order::withoutGlobalScopes()->with('pendingAdjustmentLog.requester')->findOrFail($order->id);
        [$adjustmentLog, $pendingChanges] = $this->resolvePendingContext($order);
        $requesterId = (int) ($adjustmentLog?->requester_id ?: data_get($pendingChanges, 'requested_by.id'));

        DB::transaction(function () use ($order, $admin, $adjustmentLog, $pendingChanges, $requesterId, $reason) {
            $oldValues = $order->toArray();
            $proposed = $adjustmentLog?->proposed_payload ?? ($pendingChanges['proposed'] ?? []);
            $this->deleteStagedAttachments($proposed['attachments'] ?? []);

            $order->update([
                'status' => $adjustmentLog?->previous_status ?: ($order->pending_change_original_status ?: ($pendingChanges['previous_status'] ?? 'draft')),
                'pending_changes' => null,
                'pending_change_requested_by' => null,
                'pending_change_requested_at' => null,
                'pending_change_original_status' => null,
            ]);

            if ($adjustmentLog) {
                $adjustmentLog->update([
                    'status' => 'needs_revision',
                    'reviewer_id' => $admin->id,
                    'review_notes' => $reason,
                    'reviewed_at' => now(),
                ]);
            }

            AuditLog::log(
                'pending_change_revision_requested',
                $order,
                $oldValues,
                [
                    'status' => $order->status,
                    'reason' => $reason,
                ],
                $pendingChanges['changed_fields'] ?? []
            );

            $requester = $adjustmentLog?->requester ?: User::find($requesterId);
            if ($requester) {
                $this->notifications->send($requester, new OrderChangeDecisionNotification($order->fresh(), 'revision_requested', $reason));
            }
        });
    }

    public function stageAttachments(array $files, string $type): array
    {
        $disk = config('workflow.uploads_disk', 'public');
        $folder = trim(config('workflow.uploads_disk', 'public') === 'public' ? 'pending_changes' : 'pending_changes', '/');
        $entries = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store($folder, $disk);

            $entries[] = [
                'type' => $type,
                'path' => $path,
                'file_name' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        return $entries;
    }

    private function syncFinancialValues(Order $order): void
    {
        if ($this->pricing->hasCompleteFactoryItemPricing($order)) {
            $this->pricing->syncOrderPricing($order, (float) ($order->profit_margin_percentage ?: Setting::get('default_profit_margin', 20)));

            return;
        }

        $quantity = max(1, (int) ($order->quantity ?: 1));
        $factoryCost = (float) ($order->factory_cost ?: 0);
        $sellingPrice = (float) ($order->selling_price ?: 0);

        if ($sellingPrice <= 0 && $factoryCost > 0) {
            $margin = (float) ($order->profit_margin_percentage ?: Setting::get('default_profit_margin', 20));
            $sellingPrice = round($factoryCost * (1 + ($margin / 100)), 2);
            $order->selling_price = $sellingPrice;
            $order->profit_margin_percentage = $margin;
        }

        if ($sellingPrice <= 0 && $factoryCost <= 0) {
            return;
        }

        $totalPrice = round($sellingPrice * $quantity, 2);
        $netProfit = round(($sellingPrice - $factoryCost) * $quantity, 2);

        $order->total_price = $totalPrice;
        $order->final_price = $totalPrice;
        $order->net_profit = $netProfit;
    }

    private function notifyAdmins(Order $order, User $requester, array $pendingChanges): void
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();

        $this->notifications->send($admins, new OrderChangeRequestedNotification($order, $requester, $pendingChanges));
    }

    private function persistApprovedAttachments(Order $order, array $attachments, int $uploadedBy): void
    {
        foreach ($attachments as $attachment) {
            Attachment::create([
                'order_id' => $order->id,
                'uploaded_by' => $uploadedBy,
                'file_name' => $attachment['file_name'] ?? basename((string) ($attachment['path'] ?? '')),
                'original_name' => $attachment['original_name'] ?? 'attachment',
                'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                'file_size' => (int) ($attachment['file_size'] ?? 0),
                'path' => $attachment['path'] ?? '',
                'type' => $attachment['type'] ?? 'sales_upload',
            ]);
        }
    }

    private function deleteStagedAttachments(array $attachments): void
    {
        $disk = config('workflow.uploads_disk', 'public');

        foreach ($attachments as $attachment) {
            if (!empty($attachment['path'])) {
                Storage::disk($disk)->delete($attachment['path']);
            }
        }
    }

    private function createAdjustmentLog(Order $order, User $requester, array $pendingChanges): AdjustmentLog
    {
        return AdjustmentLog::create([
            'order_id' => $order->id,
            'requester_id' => $requester->id,
            'requester_role' => $requester->role,
            'type' => $pendingChanges['type'] ?? 'adjustment',
            'status' => 'pending',
            'previous_status' => $pendingChanges['previous_status'] ?? $order->status,
            'target_status' => $pendingChanges['target_status'] ?? $order->status,
            'current_payload' => $pendingChanges['current'] ?? null,
            'proposed_payload' => $pendingChanges['proposed'] ?? null,
            'changed_fields' => $pendingChanges['changed_fields'] ?? [],
        ]);
    }

    private function resolvePendingContext(Order $order): array
    {
        $adjustmentLog = $order->relationLoaded('pendingAdjustmentLog')
            ? $order->pendingAdjustmentLog
            : $order->pendingAdjustmentLog()->with('requester')->first();

        return [$adjustmentLog, $order->pending_changes ?? []];
    }

    private function snapshotOrder(Order $order): array
    {
        $order->loadMissing(['customer', 'items', 'attachments']);

        return [
            'customer' => [
                'full_name' => optional($order->customer)->full_name,
                'address' => optional($order->customer)->address,
                'phone' => optional($order->customer)->phone,
                'email' => optional($order->customer)->email,
            ],
            'order' => [
                'customer_name' => $order->customer_name,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'specifications' => $order->specifications,
                'customer_notes' => $order->customer_notes,
                'supplier_name' => $order->supplier_name,
                'product_code' => $order->product_code,
                'factory_cost' => $order->factory_cost,
                'production_days' => $order->production_days,
                'selling_price' => $order->selling_price,
                'profit_margin_percentage' => $order->profit_margin_percentage,
                'final_price' => $order->final_price,
                'total_price' => $order->total_price,
                'net_profit' => $order->net_profit,
                'status' => $order->status,
            ],
            'items' => $order->resolvedItems()->map(fn ($item) => [
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'description' => $item->description,
                'supplier_name' => $item->supplier_name,
                'product_code' => $item->product_code,
                'unit_cost' => $item->unit_cost,
            ])->values()->all(),
        ];
    }

    private function detectChangedFields(array $current, array $proposed): array
    {
        $currentFlat = Arr::dot($current);
        $proposedFlat = Arr::dot($proposed);
        $fields = [];

        foreach (array_unique(array_merge(array_keys($currentFlat), array_keys($proposedFlat))) as $key) {
            $currentValue = $currentFlat[$key] ?? null;
            $proposedValue = $proposedFlat[$key] ?? null;

            if ($currentValue != $proposedValue) {
                $fields[] = $key;
            }
        }

        return array_values(array_unique($fields));
    }
}
