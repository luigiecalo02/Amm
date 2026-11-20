<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\Person;
use App\Notifications\Channels\WebPushChannel;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BirthdayUpcomingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Person $person,
        public Organization $organization,
        public CarbonInterface $occursOn,
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
     * @return array{title: string, body: string, url: string, person_id: string, occurs_on: string}
     */
    private function payload(): array
    {
        $when = $this->occursOn->locale(app()->getLocale())->translatedFormat('j \d\e F');
        $age = $this->occursOn->year - $this->person->birth_date?->year;
        $name = $this->person->full_name;
        $years = is_int($age) && $age > 0 ? " cumple {$age} años" : '';

        return [
            'title' => 'Cumpleaños próximo',
            'body' => "{$name}{$years} el {$when}.",
            'url' => $this->url,
            'person_id' => $this->person->id,
            'occurs_on' => $this->occursOn->toDateString(),
        ];
    }
}
