<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class BirthdayOccurrence
{
    public function nextOnOrAfter(CarbonInterface $birthDate, CarbonInterface $from): CarbonImmutable
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $candidate = $this->onYear($birthDate, $start->year);

        if ($candidate->lt($start)) {
            return $this->onYear($birthDate, $start->year + 1);
        }

        return $candidate;
    }

    private function onYear(CarbonInterface $birthDate, int $year): CarbonImmutable
    {
        $month = $birthDate->month;
        $day = $birthDate->day;

        if ($month === 2 && $day === 29 && ! CarbonImmutable::create($year, 1, 1)->isLeapYear()) {
            $day = 28;
        }

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
