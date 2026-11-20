<?php

namespace App\Support;

use App\Models\Organization;

final class BirthdayAlertRules
{
    public const DEFAULT_DAYS = 7;

    public function daysBefore(Organization $organization): int
    {
        $stored = $organization->settings['birthday_alerts']['days'] ?? self::DEFAULT_DAYS;

        return $this->days(is_numeric($stored) ? $stored : self::DEFAULT_DAYS);
    }

    public function save(Organization $organization, int $days): void
    {
        $settings = $organization->settings ?? [];
        $settings['birthday_alerts'] = ['days' => $this->days($days)];
        $organization->forceFill(['settings' => $settings])->save();
    }

    private function days(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_DAYS;
        }

        return max(1, min(365, (int) $value));
    }
}
