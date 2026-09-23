<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEventStatus;
use App\Enums\PlannedMovementStatus;
use App\Enums\RecurrenceOccurrenceStatus;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\RecurrenceOccurrence;
use App\Models\RecurrenceTemplate;
use App\Services\Recurrences\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class BuildMonthlyCalendar
{
    public function __construct(private readonly RecurrenceSchedule $schedule) {}

    /**
     * @return array{
     *     month:string,
     *     starts_on:string,
     *     ends_on:string,
     *     events:list<array<string, mixed>>
     * }
     */
    public function handle(
        Project $project,
        CarbonInterface $month,
        ?CarbonInterface $today = null,
    ): array {
        $timezone = $project->timezone ?: 'Europe/Madrid';
        $startsOn = CarbonImmutable::parse($month->toDateString(), $timezone)->startOfMonth();
        $endsOn = $startsOn->endOfMonth()->startOfDay();
        $today = $today === null
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::parse($today->toDateString(), $timezone)->startOfDay();

        $events = [
            ...$this->manualMovementEvents($project, $startsOn, $endsOn),
            ...$this->plannedMovementEvents($project, $startsOn, $endsOn, $today),
            ...$this->recurrenceEvents($project, $startsOn, $endsOn, $today),
        ];

        $sourceOrder = ['planned_movement' => 0, 'recurrence' => 1, 'movement' => 2];
        usort($events, fn (array $left, array $right): int => [
            $left['scheduled_on'],
            $sourceOrder[$left['source']],
            mb_strtolower($left['concept']),
            $left['id'],
        ] <=> [
            $right['scheduled_on'],
            $sourceOrder[$right['source']],
            mb_strtolower($right['concept']),
            $right['id'],
        ]);

        return [
            'month' => $startsOn->format('Y-m'),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'events' => $events,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function manualMovementEvents(
        Project $project,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
    ): array {
        return $project->movements()
            ->with('tags')
            ->whereNull('trashed_at')
            ->where('show_in_calendar', true)
            ->whereNull('recurrence_template_id')
            ->whereDoesntHave('plannedMovement')
            ->whereBetween('occurred_on', [$startsOn->toDateString(), $endsOn->toDateString()])
            ->get()
            ->map(fn (Movement $movement): array => $this->event(
                id: 'movement:'.$movement->id,
                source: 'movement',
                sourceId: $movement->id,
                scheduledOn: $movement->occurred_on->toDateString(),
                effectiveOn: $movement->occurred_on->toDateString(),
                status: CalendarEventStatus::Completed,
                type: $movement->type->value,
                concept: $movement->concept,
                amountCents: $movement->amount_cents,
                plannedAmountCents: null,
                actualAmountCents: $movement->amount_cents,
                categoryId: $movement->category_id,
                subcategoryId: $movement->subcategory_id,
                accountId: $movement->financial_account_id,
                destinationAccountId: $movement->destination_account_id,
                paidByUserId: $movement->paid_by_user_id,
                movementId: $movement->id,
                tagIds: $movement->tags->modelKeys(),
            ))
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function plannedMovementEvents(
        Project $project,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        CarbonImmutable $today,
    ): array {
        return $project->plannedMovements()
            ->with(['movement', 'tags'])
            ->whereBetween('due_on', [$startsOn->toDateString(), $endsOn->toDateString()])
            ->get()
            ->map(function (PlannedMovement $planned) use ($today): array {
                $movement = $planned->movement;
                $effectiveOn = $movement?->occurred_on?->toDateString();
                $status = match ($planned->status) {
                    PlannedMovementStatus::Completed => CalendarEventStatus::Completed,
                    PlannedMovementStatus::Cancelled => CalendarEventStatus::Cancelled,
                    PlannedMovementStatus::Pending => $this->pendingStatus($planned->due_on, $today),
                };

                return $this->event(
                    id: 'planned_movement:'.$planned->id,
                    source: 'planned_movement',
                    sourceId: $planned->id,
                    scheduledOn: $planned->due_on->toDateString(),
                    effectiveOn: $effectiveOn,
                    status: $status,
                    type: $movement?->type->value ?? $planned->type->value,
                    concept: $planned->concept,
                    amountCents: $movement?->amount_cents ?? $planned->amount_cents,
                    plannedAmountCents: $planned->amount_cents,
                    actualAmountCents: $movement?->amount_cents,
                    categoryId: $movement?->category_id ?? $planned->category_id,
                    subcategoryId: $movement?->subcategory_id ?? $planned->subcategory_id,
                    accountId: $movement?->financial_account_id ?? $planned->financial_account_id,
                    destinationAccountId: $movement?->destination_account_id ?? $planned->destination_account_id,
                    paidByUserId: $movement?->paid_by_user_id ?? $planned->paid_by_user_id,
                    movementId: $movement?->id,
                    plannedMovementId: $planned->id,
                    punctuality: $this->punctuality($planned->due_on->toDateString(), $effectiveOn),
                    tagIds: $planned->tags->modelKeys(),
                );
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function recurrenceEvents(
        Project $project,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        CarbonImmutable $today,
    ): array {
        $templates = $project->recurrenceTemplates()
            ->with([
                'tags',
                'occurrences' => fn ($query) => $query
                    ->whereBetween('scheduled_on', [$startsOn->toDateString(), $endsOn->toDateString()])
                    ->with('movement'),
            ])
            ->get();
        $events = [];

        foreach ($templates as $template) {
            $recordedDates = [];
            foreach ($template->occurrences as $occurrence) {
                $recordedDates[$occurrence->scheduled_on->toDateString()] = true;
                $events[] = $this->recurrenceOccurrenceEvent($template, $occurrence);
            }

            if ($template->paused_at !== null) {
                continue;
            }

            foreach ($this->schedule->datesBetween($template, $startsOn, $endsOn) as $scheduledOn) {
                if (isset($recordedDates[$scheduledOn->toDateString()])) {
                    continue;
                }

                $events[] = $this->event(
                    id: 'recurrence:'.$template->id.':'.$scheduledOn->toDateString(),
                    source: 'recurrence',
                    sourceId: $template->id,
                    scheduledOn: $scheduledOn->toDateString(),
                    effectiveOn: null,
                    status: $this->pendingStatus($scheduledOn, $today),
                    type: $template->type->value,
                    concept: $template->concept,
                    amountCents: $template->amount_cents,
                    plannedAmountCents: $template->amount_cents,
                    actualAmountCents: null,
                    categoryId: $template->category_id,
                    subcategoryId: $template->subcategory_id,
                    accountId: $template->financial_account_id,
                    destinationAccountId: $template->destination_account_id,
                    paidByUserId: $template->paid_by_user_id,
                    recurrenceTemplateId: $template->id,
                    tagIds: $template->tags->modelKeys(),
                );
            }
        }

        return $events;
    }

    /** @return array<string, mixed> */
    private function recurrenceOccurrenceEvent(
        RecurrenceTemplate $template,
        RecurrenceOccurrence $occurrence,
    ): array {
        $movement = $occurrence->movement;
        $completed = $occurrence->status === RecurrenceOccurrenceStatus::Generated;
        $effectiveOn = $movement?->occurred_on?->toDateString();

        return $this->event(
            id: 'recurrence_occurrence:'.$occurrence->id,
            source: 'recurrence',
            sourceId: $template->id,
            scheduledOn: $occurrence->scheduled_on->toDateString(),
            effectiveOn: $effectiveOn,
            status: $completed ? CalendarEventStatus::Completed : CalendarEventStatus::Skipped,
            type: $movement?->type->value ?? $template->type->value,
            concept: $movement?->concept ?? $template->concept,
            amountCents: $movement?->amount_cents ?? $template->amount_cents,
            plannedAmountCents: $template->amount_cents,
            actualAmountCents: $completed ? $movement?->amount_cents : null,
            categoryId: $movement?->category_id ?? $template->category_id,
            subcategoryId: $movement?->subcategory_id ?? $template->subcategory_id,
            accountId: $movement?->financial_account_id ?? $template->financial_account_id,
            destinationAccountId: $movement?->destination_account_id ?? $template->destination_account_id,
            paidByUserId: $movement?->paid_by_user_id ?? $template->paid_by_user_id,
            movementId: $movement?->id,
            recurrenceTemplateId: $template->id,
            recurrenceOccurrenceId: $occurrence->id,
            punctuality: $this->punctuality($occurrence->scheduled_on->toDateString(), $effectiveOn),
            tagIds: $template->tags->modelKeys(),
        );
    }

    /** @return array<string, mixed> */
    private function event(
        string $id,
        string $source,
        int $sourceId,
        string $scheduledOn,
        ?string $effectiveOn,
        CalendarEventStatus $status,
        string $type,
        string $concept,
        int $amountCents,
        ?int $plannedAmountCents,
        ?int $actualAmountCents,
        ?int $categoryId,
        ?int $subcategoryId,
        ?int $accountId,
        ?int $destinationAccountId,
        ?int $paidByUserId,
        ?int $movementId = null,
        ?int $plannedMovementId = null,
        ?int $recurrenceTemplateId = null,
        ?int $recurrenceOccurrenceId = null,
        ?string $punctuality = null,
        array $tagIds = [],
    ): array {
        return [
            'id' => $id,
            'source' => $source,
            'source_id' => $sourceId,
            'scheduled_on' => $scheduledOn,
            'effective_on' => $effectiveOn,
            'status' => $status->value,
            'status_label' => $status->label(),
            'punctuality' => $punctuality,
            'type' => $type,
            'concept' => $concept,
            'amount_cents' => $amountCents,
            'planned_amount_cents' => $plannedAmountCents,
            'actual_amount_cents' => $actualAmountCents,
            'is_estimated' => in_array($status, [
                CalendarEventStatus::Planned,
                CalendarEventStatus::Today,
                CalendarEventStatus::Overdue,
            ], true),
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'financial_account_id' => $accountId,
            'destination_account_id' => $destinationAccountId,
            'paid_by_user_id' => $paidByUserId,
            'movement_id' => $movementId,
            'planned_movement_id' => $plannedMovementId,
            'recurrence_template_id' => $recurrenceTemplateId,
            'recurrence_occurrence_id' => $recurrenceOccurrenceId,
            'tag_ids' => array_values($tagIds),
        ];
    }

    private function pendingStatus(CarbonInterface $scheduledOn, CarbonImmutable $today): CalendarEventStatus
    {
        $scheduledOn = CarbonImmutable::parse($scheduledOn->toDateString(), $today->timezone)->startOfDay();

        if ($scheduledOn->isSameDay($today)) {
            return CalendarEventStatus::Today;
        }

        return $scheduledOn->lessThan($today)
            ? CalendarEventStatus::Overdue
            : CalendarEventStatus::Planned;
    }

    private function punctuality(string $scheduledOn, ?string $effectiveOn): ?string
    {
        if ($effectiveOn === null) {
            return null;
        }

        return match ($effectiveOn <=> $scheduledOn) {
            -1 => 'early',
            0 => 'on_time',
            1 => 'late',
        };
    }
}
