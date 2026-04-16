<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly string $title,
        private readonly string $message,
        private readonly string $url,
        private readonly array $extra = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'url' => $this->url,
        ], $this->extra);
    }

    public function toPushPayload(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
