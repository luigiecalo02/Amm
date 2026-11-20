<?php

namespace App\Support;

use App\Models\Organization;

final class DocumentAlertRules
{
    /**
     * @var array<string, array{label: string, days: int}>
     */
    public const DEFAULTS = [
        'licencia' => ['label' => 'Licencia de conducción', 'days' => 90],
        'soat' => ['label' => 'SOAT', 'days' => 30],
        'tecnomecanica' => ['label' => 'Tecnomecánica', 'days' => 30],
    ];

    public const DEFAULT_REPEAT_EVERY = 1;

    /**
     * @return list<array{slug: string, label: string, days: int}>
     */
    public function forOrganization(Organization $organization): array
    {
        $stored = $organization->settings['document_alerts'] ?? [];

        return collect(self::DEFAULTS)
            ->map(function (array $default, string $slug) use ($stored): array {
                $days = is_array($stored) ? ($stored[$slug] ?? $default['days']) : $default['days'];

                return [
                    'slug' => $slug,
                    'label' => $default['label'],
                    'days' => $this->days($days, $default['days']),
                ];
            })
            ->values()
            ->all();
    }

    public function repeatEvery(Organization $organization): int
    {
        $stored = $organization->settings['document_alerts']['repeat_every'] ?? self::DEFAULT_REPEAT_EVERY;

        return $this->interval($stored);
    }

    public function daysBefore(Organization $organization, string $slug): int
    {
        $rule = collect($this->forOrganization($organization))->firstWhere('slug', $slug);

        return $rule['days'] ?? self::DEFAULTS[$slug]['days'] ?? 30;
    }

    /**
     * @param  array<string, mixed>  $daysBySlug
     */
    public function save(Organization $organization, array $daysBySlug): void
    {
        $alerts = [];

        foreach (self::DEFAULTS as $slug => $default) {
            $alerts[$slug] = $this->days($daysBySlug[$slug] ?? $default['days'], $default['days']);
        }

        $alerts['repeat_every'] = array_key_exists('repeat_every', $daysBySlug)
            ? $this->interval($daysBySlug['repeat_every'])
            : $this->repeatEvery($organization);

        $settings = $organization->settings ?? [];
        $settings['document_alerts'] = $alerts;
        $organization->forceFill(['settings' => $settings])->save();
    }

    private function days(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(1, min(365, (int) $value));
    }

    private function interval(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_REPEAT_EVERY;
        }

        return max(1, min(30, (int) $value));
    }
}
