<?php

namespace App\Listeners;

use App\Events\DocumentCreated;
use App\Events\DocumentDownloaded;
use App\Events\MemberAdded;
use App\Events\PersonCreated;
use App\Events\UserAssignedToOrganization;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class WriteAuditLog
{
    public function handle(object $event): void
    {
        $definition = match ($event::class) {
            PersonCreated::class => ['action' => 'person.created', 'model' => 'person', 'id' => $event->person->id],
            MemberAdded::class => ['action' => 'member.added', 'model' => 'member', 'id' => $event->member->id],
            DocumentCreated::class => ['action' => 'document.created', 'model' => 'document', 'id' => $event->document->id],
            DocumentDownloaded::class => ['action' => 'document.downloaded', 'model' => 'document', 'id' => $event->document->id],
            UserAssignedToOrganization::class => ['action' => 'user.assigned', 'model' => 'user', 'id' => $event->user->id],
            default => null,
        };

        if (! $definition) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => $event->actor?->id,
            'organization_id' => $event->organization->id ?? null,
            'action' => $definition['action'],
            'model' => $definition['model'],
            'model_id' => $definition['id'],
            'old_values' => null,
            'new_values' => null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
