<?php

use App\Models\Document;
use App\Notifications\DocumentExpiringNotification;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    Document::query()
        ->with(['type', 'person.users'])
        ->expiringSoon(15)
        ->each(function (Document $document): void {
            $document->person?->users->each(
                fn ($user) => $user->notify(new DocumentExpiringNotification($document))
            );
        });
})->dailyAt('07:00');
