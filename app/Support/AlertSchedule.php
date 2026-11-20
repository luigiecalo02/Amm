<?php

namespace App\Support;

use App\Models\Organization;
use Carbon\CarbonInterface;

final class AlertSchedule
{
    public const DEFAULT = '07:00';

    public function timeFor(Organization $organization): string
    {
        $stored = $organization->settings['alert_time'] ?? self::DEFAULT;

        return $this->normalize(is_string($stored) ? $stored : self::DEFAULT);
    }

    public function save(Organization $organization, ?string $time): void
    {
        if ($time === null || $time === '') {
            return;
        }

        $organization->refresh();
        $settings = $organization->settings ?? [];
        $settings['alert_time'] = $this->normalize($time);
        $organization->forceFill(['settings' => $settings])->save();
    }

    public function isDue(Organization $organization, ?CarbonInterface $moment = null): bool
    {
        $moment ??= now();
        $scheduled = $moment->copy()->setTimeFromTimeString($this->timeFor($organization));

        return $moment->greaterThanOrEqualTo($scheduled);
    }

    private function normalize(string $time): string
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $time, $matches)) {
            return self::DEFAULT;
        }

        return $matches[1].':'.$matches[2];
    }
}
