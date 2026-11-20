<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Collection;

final class UserAlertPreferences
{
    public const DOCUMENTS = 'document_alerts';

    public const BIRTHDAYS = 'birthday_alerts';

    /**
     * @return array{documents: bool, birthdays: bool}
     */
    public function forUser(User $user, Organization $organization): array
    {
        $preference = $this->find($user, $organization);

        return [
            'documents' => $preference?->document_alerts ?? true,
            'birthdays' => $preference?->birthday_alerts ?? true,
        ];
    }

    public function save(User $user, Organization $organization, bool $documents, bool $birthdays): void
    {
        UserNotificationPreference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'document_alerts' => $documents,
                'birthday_alerts' => $birthdays,
            ],
        );
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public function onlyEnabled(Collection $users, Organization $organization, string $channel): Collection
    {
        if ($users->isEmpty() || ! in_array($channel, [self::DOCUMENTS, self::BIRTHDAYS], true)) {
            return $users;
        }

        $disabled = UserNotificationPreference::query()
            ->where('organization_id', $organization->id)
            ->whereIn('user_id', $users->modelKeys())
            ->where($channel, false)
            ->pluck('user_id');

        return $users
            ->reject(fn (User $user): bool => $disabled->contains($user->id))
            ->values();
    }

    private function find(User $user, Organization $organization): ?UserNotificationPreference
    {
        return UserNotificationPreference::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
