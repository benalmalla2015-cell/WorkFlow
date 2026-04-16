<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class UserNotificationService
{
    public function __construct(private FirebasePushService $firebasePush)
    {
    }

    public function send(iterable|User $recipients, Notification $notification): void
    {
        $users = $this->normalizeRecipients($recipients);

        foreach ($users as $user) {
            $user->notify($notification);

            if (method_exists($notification, 'toPushPayload')) {
                $this->firebasePush->sendToUser($user, $notification->toPushPayload($user));
            }
        }
    }

    private function normalizeRecipients(iterable|User $recipients): Collection
    {
        if ($recipients instanceof User) {
            return collect([$recipients]);
        }

        return collect($recipients)
            ->filter(fn ($recipient) => $recipient instanceof User)
            ->unique('id')
            ->values();
    }
}
