<?php

declare(strict_types=1);

namespace App\Services\Recurrences;

use App\Enums\RecurrenceFrequency;
use App\Models\RecurrenceTemplate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class RecurrenceSchedule
{
    public function nextDate(RecurrenceTemplate $template, CarbonInterface $current): CarbonImmutable
    {
        $current = $this->date($current);

        return match ($template->frequency) {
            RecurrenceFrequency::Daily => $current->addDay(),
            RecurrenceFrequency::Weekly => $current->addWeek(),
            RecurrenceFrequency::Monthly => $this->dateWithAnchor(
                $current->startOfMonth()->addMonth(),
                (int) $template->anchor_day,
            ),
            RecurrenceFrequency::Annual => $this->dateWithAnchor(
                CarbonImmutable::create(
                    $current->year + 1,
                    $template->start_on->month,
                    1,
                    0,
                    0,
                    0,
                    'Europe/Madrid',
                ),
                (int) $template->anchor_day,
            ),
        };
    }

    /** @return list<CarbonImmutable> */
    public function datesBetween(
        RecurrenceTemplate $template,
        CarbonInterface $from,
        CarbonInterface $through,
    ): array {
        $from = $this->date($from);
        $through = $this->date($through);

        if ($through->lessThan($from)) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la inicial.');
        }

        if ($template->next_occurrence_on === null) {
            return [];
        }

        $cursor = CarbonImmutable::parse(
            $template->next_occurrence_on->toDateString(),
            'Europe/Madrid',
        )->startOfDay();
        $endsOn = $template->ends_on === null
            ? null
            : CarbonImmutable::parse($template->ends_on->toDateString(), 'Europe/Madrid')->startOfDay();

        while ($cursor->lessThan($from)) {
            $cursor = $this->nextDate($template, $cursor);

            if ($endsOn !== null && $cursor->greaterThan($endsOn)) {
                return [];
            }
        }

        $dates = [];
        while ($cursor->lessThanOrEqualTo($through) && ($endsOn === null || $cursor->lessThanOrEqualTo($endsOn))) {
            $dates[] = $cursor;
            $cursor = $this->nextDate($template, $cursor);
        }

        return $dates;
    }

    private function date(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), 'Europe/Madrid')->startOfDay();
    }

    private function dateWithAnchor(CarbonImmutable $month, int $anchorDay): CarbonImmutable
    {
        return $month->day(min($anchorDay, $month->daysInMonth));
    }
}
