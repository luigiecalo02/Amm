<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushSender
{
    /**
     * @param  array<string, string>  $payload
     */
    public function send(User $user, array $payload): void
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! is_string($publicKey) || $publicKey === '' || ! is_string($privateKey) || $privateKey === '') {
            return;
        }

        $this->ensureOpenSslConfig();

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('services.webpush.subject'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo preparar el aviso push.', ['message' => $exception->getMessage()]);

            return;
        }

        try {
            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    json_encode($payload, JSON_THROW_ON_ERROR)
                );
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $user->pushSubscriptions()->where('endpoint', $report->getEndpoint())->delete();
                }
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar el aviso push.', ['message' => $exception->getMessage()]);
        }
    }

    private function ensureOpenSslConfig(): void
    {
        if (getenv('OPENSSL_CONF')) {
            return;
        }

        $candidate = PHP_BINDIR.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        if (is_file($candidate)) {
            putenv('OPENSSL_CONF='.$candidate);
        }
    }
}
