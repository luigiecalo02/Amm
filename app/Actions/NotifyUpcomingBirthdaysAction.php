<?php

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\BirthdayNotice;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\BirthdayUpcomingNotification;
use App\Services\Permissions\PermissionService;
use App\Support\AlertSchedule;
use App\Support\BirthdayAlertRules;
use App\Support\BirthdayOccurrence;
use App\Support\UserAlertPreferences;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class NotifyUpcomingBirthdaysAction
{
    /**
     * @var list<string>
     */
    private const BOARD_ROLES = ['director', 'subdirector', 'secretario', 'capellan', 'tesorero'];

    public function __construct(
        private readonly BirthdayAlertRules $rules,
        private readonly BirthdayOccurrence $occurrence,
        private readonly PermissionService $permissions,
        private readonly AlertSchedule $schedule,
        private readonly UserAlertPreferences $preferences,
    ) {}

    public function execute(bool $onlyWhenDue = false): int
    {
        $sent = 0;
        $today = now()->startOfDay();

        Organization::query()->orderBy('name')->each(function (Organization $organization) use (&$sent, $today, $onlyWhenDue): void {
            if ($onlyWhenDue && ! $this->schedule->isDue($organization)) {
                return;
            }

            $sent += $this->notifyOrganization($organization, $today);
        });

        return $sent;
    }

    private function notifyOrganization(Organization $organization, CarbonInterface $today): int
    {
        $this->permissions->ensureMissingSystemRoles($organization);

        $recipients = $this->preferences->onlyEnabled(
            $this->recipients($organization),
            $organization,
            UserAlertPreferences::BIRTHDAYS,
        );

        if ($recipients->isEmpty()) {
            return 0;
        }

        $until = $today->copy()->addDays($this->rules->daysBefore($organization))->startOfDay();
        $sent = 0;

        Person::query()
            ->whereNotNull('birth_date')
            ->where('status', PersonStatus::Active)
            ->whereHas('memberships', function ($memberships) use ($organization): void {
                $memberships->where('organization_id', $organization->id)
                    ->where('status', MembershipStatus::Active);
            })
            ->orderBy('last_name')
            ->each(function (Person $person) use ($organization, $today, $until, $recipients, &$sent): void {
                if ($person->birth_date === null) {
                    return;
                }

                $next = $this->occurrence->nextOnOrAfter($person->birth_date, $today);

                if ($next->greaterThan($until) || $this->alreadyNotified($organization, $person, $next)) {
                    return;
                }

                $notification = new BirthdayUpcomingNotification(
                    $person,
                    $organization,
                    $next,
                    route('people.show', [$organization, $person], false),
                );

                $recipients->each(fn (User $user) => $user->notify($notification));

                BirthdayNotice::query()->create([
                    'organization_id' => $organization->id,
                    'person_id' => $person->id,
                    'occurs_on' => $next->toDateString(),
                ]);

                $sent++;
            });

        return $sent;
    }

    private function alreadyNotified(Organization $organization, Person $person, CarbonInterface $occursOn): bool
    {
        return BirthdayNotice::query()
            ->where('organization_id', $organization->id)
            ->where('person_id', $person->id)
            ->whereDate('occurs_on', $occursOn->toDateString())
            ->exists();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Organization $organization): Collection
    {
        return User::query()
            ->whereHas('organizationRoles', function ($roles) use ($organization): void {
                $roles->where('organization_id', $organization->id)
                    ->whereHas('role', fn ($role) => $role->whereIn('slug', self::BOARD_ROLES));
            })
            ->get();
    }
}
