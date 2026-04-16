<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly User $requester,
        private readonly array $pendingChanges
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'طلب تعديل جديد بانتظار الاعتماد',
            'message' => sprintf(
                'قام %s (%s) بإرسال تعديلات على الطلب %s وتنتظر موافقتك.',
                $this->requester->name,
                strtoupper((string) $this->requester->role),
                $this->order->order_number
            ),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'requested_by' => $this->requester->name,
            'requested_by_role' => $this->requester->role,
            'changed_fields' => $this->pendingChanges['changed_fields'] ?? [],
            'url' => route('admin.orders.pending-changes.review', $this->order),
        ];
    }

    public function toPushPayload(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
