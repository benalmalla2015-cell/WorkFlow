<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NotificationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(5)->get();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'تنبيه جديد',
                    'message' => $notification->data['message'] ?? '',
                    'reason' => $notification->data['reason'] ?? null,
                    'url' => route('notifications.open', $notification->id),
                    'target_url' => $notification->data['url'] ?? route('dashboard'),
                    'is_read' => $notification->read_at !== null,
                    'created_at_human' => optional($notification->created_at)->diffForHumans(),
                ];
            })->values(),
        ]);
    }

    public function syncFirebaseToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:5000'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        NotificationToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'provider' => 'fcm_web',
                'device_name' => $validated['device_name'] ?? substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['status' => 'ok']);
    }

    public function markAsRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function open(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($notificationId)->firstOrFail();

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return redirect()->to($notification->data['url'] ?? route('dashboard'));
    }

    public function serviceWorker(): Response
    {
        return response()
            ->view('notifications.firebase-messaging-sw', [
                'firebaseConfig' => config('firebase.web'),
            ])
            ->header('Content-Type', 'application/javascript');
    }
}
