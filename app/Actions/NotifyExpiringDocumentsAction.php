<?php

namespace App\Actions;

use App\Models\Document;
use App\Models\DocumentExpirationNotice;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\DocumentExpiringNotification;
use App\Support\AlertSchedule;
use App\Support\DocumentAlertRules;
use App\Support\UserAlertPreferences;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class NotifyExpiringDocumentsAction
{
    /**
     * @var list<string>
     */
    private const STAFF_ROLES = ['director', 'subdirector', 'secretario', 'tesorero'];

    public function __construct(
        private readonly DocumentAlertRules $rules,
        private readonly AlertSchedule $schedule,
        private readonly UserAlertPreferences $preferences,
    ) {}

    public function execute(bool $onlyWhenDue = false): int
    {
        $sent = 0;

        Organization::query()->orderBy('name')->each(function (Organization $organization) use (&$sent, $onlyWhenDue): void {
            if ($onlyWhenDue && ! $this->schedule->isDue($organization)) {
                return;
            }

            foreach ($this->rules->forOrganization($organization) as $rule) {
                $sent += $this->notifyType($organization, $rule['slug'], $rule['days']);
            }
        });

        return $sent;
    }

    private function notifyType(Organization $organization, string $slug, int $days): int
    {
        $sent = 0;
        $until = now()->addDays($days)->toDateString();

        Document::query()
            ->inOrganization($organization->id)
            ->whereHas('type', fn ($query) => $query->where('slug', $slug))
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now()->toDateString(), $until])
            ->with(['type', 'files', 'person.users', 'motorcycle.person.users', 'motorcycle'])
            ->orderBy('expiration_date')
            ->each(function (Document $document) use ($organization, &$sent): void {
                $notice = $this->noticeFor($organization, $document);

                if ($notice && ($this->documentChanged($document, $notice) || ! $this->intervalElapsed($organization, $notice))) {
                    return;
                }

                $recipients = $this->preferences->onlyEnabled(
                    $this->recipients($organization, $document->person ?? $document->motorcycle?->person),
                    $organization,
                    UserAlertPreferences::DOCUMENTS,
                );

                if ($recipients->isEmpty()) {
                    return;
                }

                $url = $this->url($organization, $document->person ?? $document->motorcycle?->person);
                $notification = new DocumentExpiringNotification($document, $organization, $url);

                $recipients->each(fn (User $user) => $user->notify($notification));
                $this->remember($organization, $document, $notice);

                $sent++;
            });

        return $sent;
    }

    private function noticeFor(Organization $organization, Document $document): ?DocumentExpirationNotice
    {
        return DocumentExpirationNotice::query()
            ->where('organization_id', $organization->id)
            ->where('document_id', $document->id)
            ->whereDate('expiration_date', $document->expiration_date)
            ->first();
    }

    private function documentChanged(Document $document, DocumentExpirationNotice $notice): bool
    {
        if ($notice->source_updated_at === null) {
            return false;
        }

        return $this->revisionAt($document)->greaterThan($notice->source_updated_at);
    }

    private function intervalElapsed(Organization $organization, DocumentExpirationNotice $notice): bool
    {
        $last = $notice->last_sent_on ?? $notice->created_at;

        if (! $last instanceof CarbonInterface) {
            return true;
        }

        $next = $last->copy()->startOfDay()->addDays($this->rules->repeatEvery($organization));

        return now()->startOfDay()->greaterThanOrEqualTo($next);
    }

    private function remember(Organization $organization, Document $document, ?DocumentExpirationNotice $notice): void
    {
        $sentOn = now()->toDateString();

        if ($notice) {
            $notice->forceFill([
                'last_sent_on' => $sentOn,
                'source_updated_at' => $notice->source_updated_at ?? $this->revisionAt($document),
            ])->save();

            return;
        }

        DocumentExpirationNotice::query()->create([
            'organization_id' => $organization->id,
            'document_id' => $document->id,
            'expiration_date' => $document->expiration_date?->toDateString(),
            'last_sent_on' => $sentOn,
            'source_updated_at' => $this->revisionAt($document),
        ]);
    }

    private function revisionAt(Document $document): CarbonInterface
    {
        $latest = $document->updated_at ?? now();

        foreach ($document->files as $file) {
            if ($file->created_at instanceof CarbonInterface && $file->created_at->greaterThan($latest)) {
                $latest = $file->created_at;
            }
        }

        return $latest;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Organization $organization, ?Person $person): Collection
    {
        $staff = User::query()
            ->whereHas('organizationRoles', function ($roles) use ($organization): void {
                $roles->where('organization_id', $organization->id)
                    ->whereHas('role', fn ($role) => $role->whereIn('slug', self::STAFF_ROLES));
            })
            ->get();

        $members = $person?->users ?? collect();

        return $staff->concat($members)->unique('id')->values();
    }

    private function url(Organization $organization, ?Person $person): string
    {
        if ($person) {
            return route('people.show', [$organization, $person]);
        }

        return route('documents.expiring', $organization);
    }
}
