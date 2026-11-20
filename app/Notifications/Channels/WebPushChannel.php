<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Notifications\WebPushSender;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(private readonly WebPushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);

        if (! is_array($payload)) {
            return;
        }

        $this->sender->send($notifiable, $payload);
    }
}
