<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Person;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public Organization $organization,
        public string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, string>
     */
    public function toWebPush(object $notifiable): array
    {
        $payload = $this->payload();

        return [
            'title' => $payload['title'],
            'body' => $payload['body'],
            'url' => $payload['url'],
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, document_id: string, document_type: string|null, expiration_date: string|null}
     */
    private function payload(): array
    {
        $type = $this->document->type?->name ?? 'Documento';
        $person = $this->document->person ?? $this->document->motorcycle?->person;
        $plate = $this->document->motorcycle?->plate;
        $who = trim(implode(' · ', array_filter([
            $person instanceof Person ? $person->full_name : null,
            filled($plate) ? $plate : null,
        ])));
        $date = $this->document->expiration_date?->toDateString();

        return [
            'title' => "{$type} por vencer",
            'body' => trim(($who !== '' ? $who.' · ' : '').($date ? "vence el {$date}" : 'tiene un vencimiento próximo')),
            'url' => $this->url,
            'document_id' => $this->document->id,
            'document_type' => $this->document->type?->name,
            'expiration_date' => $date,
        ];
    }
}
