<?php

namespace App\Http\Controllers;

use App\Models\NotificationToken;
use App\Models\Notification;
use App\Models\Order;
use App\Models\AdjustmentLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Store FCM token for the authenticated user
     */
    public function storeToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string|in:web,android,ios',
        ]);

        $user = $request->user();

        NotificationToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'fcm_token' => $validated['token'],
            ],
            [
                'device_type' => $validated['device_type'] ?? 'web',
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token registered successfully']);
    }

    /**
     * Remove FCM token
     */
    public function removeToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $user = $request->user();

        NotificationToken::where('user_id', $user->id)
            ->where('fcm_token', $validated['token'])
            ->delete();

        return response()->json(['message' => 'Token removed successfully']);
    }

    /**
     * Get user notifications
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orWhere(function ($query) use ($user) {
                // Admin gets all notifications
                if ($user->isAdmin()) {
                    $query->whereNull('user_id');
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Mark as read
        Notification::whereIn('id', $notifications->pluck('id'))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($notifications);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification)
    {
        if ($notification->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(Request $request)
    {
        $user = $request->user();

        $count = Notification::where(function ($query) use ($user) {
                $query->where('user_id', $user->id);
                if ($user->isAdmin()) {
                    $query->orWhereNull('user_id');
                }
            })
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Send notification to admins when adjustment is requested
     */
    public static function notifyAdjustmentRequested(AdjustmentLog $adjustment)
    {
        $order = $adjustment->order;
        $requester = $adjustment->requester;

        // Get all admin users
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            // Create database notification
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'adjustment_requested',
                'title' => 'طلب تعديل جديد',
                'body' => "طلب تعديل من {$requester->name} على الطلب #{$order->order_number}",
                'data' => [
                    'adjustment_id' => $adjustment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'requester_name' => $requester->name,
                    'requester_role' => $requester->role,
                ],
                'action_url' => "/admin/orders/{$order->id}",
            ]);

            // Send push notification
            self::sendPushNotification(
                $admin,
                'طلب تعديل جديد',
                "طلب تعديل من {$requester->name} على الطلب #{$order->order_number}",
                [
                    'type' => 'adjustment_requested',
                    'adjustment_id' => $adjustment->id,
                    'order_id' => $order->id,
                ]
            );
        }
    }

    /**
     * Send notification when adjustment is approved/rejected
     */
    public static function notifyAdjustmentResolved(AdjustmentLog $adjustment)
    {
        $order = $adjustment->order;
        $requester = $adjustment->requester;
        $reviewer = $adjustment->reviewer;

        $statusText = $adjustment->status === 'approved' ? 'تمت الموافقة' : 'تم الرفض';

        // Notify the requester
        Notification::create([
            'user_id' => $requester->id,
            'type' => 'adjustment_resolved',
            'title' => "{$statusText} على طلب التعديل",
            'body' => "{$statusText} على طلب التعديل للطلب #{$order->order_number} بواسطة {$reviewer->name}",
            'data' => [
                'adjustment_id' => $adjustment->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $adjustment->status,
                'reviewer_name' => $reviewer->name,
            ],
            'action_url' => "/orders/{$order->id}",
        ]);

        // Send push notification
        self::sendPushNotification(
            $requester,
            "{$statusText} على طلب التعديل",
            "{$statusText} على طلب التعديل للطلب #{$order->order_number}",
            [
                'type' => 'adjustment_resolved',
                'adjustment_id' => $adjustment->id,
                'order_id' => $order->id,
                'status' => $adjustment->status,
            ]
        );
    }

    /**
     * Send notification when order status changes
     */
    public static function notifyOrderStatusChanged(Order $order, string $oldStatus, string $newStatus)
    {
        $statusLabels = [
            'draft' => 'جديد',
            'sent_to_factory' => 'تم الإرسال للمصنع',
            'factory_pricing' => 'تسعير المصنع',
            'manager_review' => 'مراجعة المدير',
            'approved' => 'معتمد',
            'customer_approved' => 'موافقة العميل',
            'payment_confirmed' => 'تأكيد الدفع',
            'completed' => 'مكتمل',
        ];

        // Notify sales user
        if ($order->salesUser) {
            Notification::create([
                'user_id' => $order->sales_user_id,
                'type' => 'order_status_changed',
                'title' => 'تحديث حالة الطلب',
                'body' => "تم تغيير حالة الطلب #{$order->order_number} إلى: {$statusLabels[$newStatus]}",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                'action_url' => "/sales/orders/{$order->id}",
            ]);

            self::sendPushNotification(
                $order->salesUser,
                'تحديث حالة الطلب',
                "تم تغيير حالة الطلب #{$order->order_number}",
                [
                    'type' => 'order_status_changed',
                    'order_id' => $order->id,
                    'new_status' => $newStatus,
                ]
            );
        }
    }

    /**
     * Send push notification via Firebase
     */
    private static function sendPushNotification(User $user, string $title, string $body, array $data = [])
    {
        $tokens = NotificationToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $serverKey = config('services.firebase.server_key');

        if (empty($serverKey)) {
            Log::warning('Firebase server key not configured');
            return;
        }

        foreach ($tokens as $token) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => '/logo192.png',
                        'badge' => '/badge.png',
                        'sound' => 'default',
                        'click_action' => $data['action_url'] ?? '/',
                    ],
                    'data' => $data,
                    'priority' => 'high',
                ]);

                if (!$response->successful()) {
                    Log::error('FCM notification failed', [
                        'token' => $token,
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('FCM notification exception', [
                    'token' => $token,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
