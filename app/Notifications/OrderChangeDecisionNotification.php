<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderChangeDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly string $decision,
        private readonly ?string $reason = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->decision === 'approved';
        $revisionRequested = $this->decision === 'revision_requested';
        $route = $notifiable->isFactory() ? route('factory.orders.edit', $this->order) : route('sales.orders.edit', $this->order);

        return [
            'title' => $approved
                ? 'تم اعتماد التعديل'
                : ($revisionRequested ? 'مطلوب تعديل إضافي' : 'تم رفض التعديل'),
            'message' => $approved
                ? sprintf('تم اعتماد التعديل المرسل على الطلب %s وتثبيت البيانات الجديدة.', $this->order->order_number)
                : ($revisionRequested
                    ? sprintf('طلب المدير تعديلًا إضافيًا على الطلب %s قبل الاعتماد.', $this->order->order_number)
                    : sprintf('تم رفض التعديل المرسل على الطلب %s.', $this->order->order_number)),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'url' => $route,
        ];
    }

    public function toPushPayload(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
