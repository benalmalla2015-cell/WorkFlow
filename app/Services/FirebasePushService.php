<?php

namespace App\Services;

use App\Models\NotificationToken;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function sendToUser(User $user, array $payload): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $tokens = $user->notificationTokens()->pluck('token')->filter()->values();

        foreach ($tokens as $token) {
            $this->sendToToken($token, $payload);
        }
    }

    public function isConfigured(): bool
    {
        $path = (string) config('firebase.service_account_path');

        return $path !== '' && file_exists($path) && !empty(config('firebase.project_id'));
    }

    private function sendToToken(string $token, array $payload): void
    {
        try {
            $projectId = (string) config('firebase.project_id');
            $url = (string) ($payload['url'] ?? route('dashboard'));
            $title = (string) ($payload['title'] ?? 'تنبيه جديد');
            $message = (string) ($payload['message'] ?? '');
            $tag = (string) ($payload['tag'] ?? ('order-' . ($payload['order_id'] ?? 'workflow')));
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->post(sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId), [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $message,
                        ],
                        'data' => $this->normalizeDataPayload($payload),
                        'webpush' => [
                            'fcm_options' => [
                                'link' => $url,
                            ],
                            'notification' => [
                                'title' => $title,
                                'body' => $message,
                                'tag' => $tag,
                                'icon' => '/favicon.ico',
                                'badge' => '/favicon.ico',
                                'requireInteraction' => false,
                                'data' => [
                                    'url' => $url,
                                    'title' => $title,
                                    'message' => $message,
                                    'type' => (string) ($payload['type'] ?? ''),
                                    'sound_event' => (string) ($payload['sound_event'] ?? ''),
                                    'tag' => $tag,
                                    'order_id' => (string) ($payload['order_id'] ?? ''),
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                $this->handleFailedToken($token, $response->json());
            }
        } catch (\Throwable $exception) {
            Log::warning('firebase_push_failed', [
                'token' => substr($token, 0, 24),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeDataPayload(array $payload): array
    {
        $allowed = Arr::except($payload, ['changed_fields']);

        return collect($allowed)
            ->mapWithKeys(function ($value, $key) {
                if (is_scalar($value) || $value === null) {
                    return [$key => (string) $value];
                }

                return [$key => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''];
            })
            ->all();
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase.push.access_token', now()->addMinutes(50), function () {
            $serviceAccount = $this->serviceAccount();
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ], JSON_UNESCAPED_SLASHES));

            $issuedAt = time();
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $serviceAccount['token_uri'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], JSON_UNESCAPED_SLASHES));

            $signatureInput = $header . '.' . $claims;
            openssl_sign($signatureInput, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = $signatureInput . '.' . $this->base64UrlEncode($signature);

            $response = Http::asForm()->post($serviceAccount['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json();

            return (string) ($response['access_token'] ?? '');
        });
    }

    private function serviceAccount(): array
    {
        $path = (string) config('firebase.service_account_path');
        $contents = json_decode((string) file_get_contents($path), true);

        if (!is_array($contents) || empty($contents['client_email']) || empty($contents['private_key']) || empty($contents['token_uri'])) {
            throw new \RuntimeException('Firebase service account is invalid.');
        }

        return $contents;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function handleFailedToken(string $token, ?array $payload): void
    {
        $message = strtolower((string) data_get($payload, 'error.message', ''));

        if (str_contains($message, 'registration-token-not-registered') || str_contains($message, 'unregistered')) {
            NotificationToken::query()->where('token', $token)->delete();
        }

        Log::warning('firebase_push_response_failed', [
            'token' => substr($token, 0, 24),
            'error' => $payload,
        ]);
    }
}
