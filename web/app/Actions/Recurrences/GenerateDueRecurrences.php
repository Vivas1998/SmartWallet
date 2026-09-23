<?php

declare(strict_types=1);

namespace App\Actions\Recurrences;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Movements\RebuildMovementEntries;
use App\Actions\SavingsGoals\SyncGoalAllocation;
use App\Enums\GoalAllocationDirection;
use App\Enums\RecurrenceOccurrenceStatus;
use App\Models\Movement;
use App\Models\RecurrenceOccurrence;
use App\Models\RecurrenceRecoveryNotice;
use App\Models\RecurrenceTemplate;
use App\Services\Recurrences\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateDueRecurrences
{
    public function __construct(
        private readonly RebuildMovementEntries $entries,
        private readonly SyncGoalAllocation $goalAllocation,
        private readonly RecordProjectAudit $audit,
        private readonly RecurrenceSchedule $schedule,
    ) {}

    /** @return array{generated:int, projects:array<int, list<array{concept:string,date:string,amount_cents:int}>>} */
    public function handle(?CarbonImmutable $through = null, bool $createRecoveryNotice = false): array
    {
        $through ??= CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $result = ['generated' => 0, 'projects' => []];

        $templateIds = RecurrenceTemplate::query()
            ->whereNull('paused_at')
            ->whereNotNull('next_occurrence_on')
            ->whereDate('next_occurrence_on', '<=', $through->toDateString())
            ->whereHas('project', fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('next_occurrence_on')
            ->pluck('id');

        foreach ($templateIds as $templateId) {
            DB::transaction(function () use ($templateId, $through, &$result): void {
                $template = RecurrenceTemplate::query()->lockForUpdate()->find($templateId);
                if ($template === null || $template->paused_at !== null || $template->next_occurrence_on === null) {
                    return;
                }

                $template->load(['project', 'account', 'destinationAccount', 'category', 'subcategory', 'savingsGoal', 'tags']);
                if (! $this->dependenciesAreUsable($template)) {
                    $before = $template->auditSnapshot();
                    $template->update(['paused_at' => now()]);
                    $this->audit->handle($template->project, null, 'recurrence', $template->id, 'generation_paused', $before, $template->fresh()->auditSnapshot());

                    return;
                }

                while ($template->next_occurrence_on !== null && $template->next_occurrence_on->toDateString() <= $through->toDateString()) {
                    $scheduledOn = CarbonImmutable::parse($template->next_occurrence_on->toDateString(), 'Europe/Madrid');
                    if ($template->ends_on !== null && $scheduledOn->toDateString() > $template->ends_on->toDateString()) {
                        $template->update(['next_occurrence_on' => null]);
                        break;
                    }

                    $occurrence = RecurrenceOccurrence::firstOrCreate(
                        ['recurrence_template_id' => $template->id, 'scheduled_on' => $scheduledOn->toDateString()],
                        ['status' => RecurrenceOccurrenceStatus::Generated, 'processed_at' => now()],
                    );

                    if ($occurrence->wasRecentlyCreated) {
                        $movement = $this->createMovement($template, $occurrence, $scheduledOn);
                        $occurrence->update(['movement_id' => $movement->id]);
                        $result['generated']++;
                        $result['projects'][$template->project_id][] = [
                            'concept' => $movement->concept,
                            'date' => $scheduledOn->toDateString(),
                            'amount_cents' => $movement->amount_cents,
                        ];
                    }

                    $next = $this->schedule->nextDate($template, $scheduledOn);
                    $template->update([
                        'next_occurrence_on' => $template->ends_on !== null && $next->toDateString() > $template->ends_on->toDateString() ? null : $next->toDateString(),
                    ]);
                    $template->refresh();
                }
            }, 3);
        }

        if ($createRecoveryNotice) {
            foreach ($result['projects'] as $projectId => $details) {
                RecurrenceRecoveryNotice::create([
                    'project_id' => $projectId,
                    'generated_count' => count($details),
                    'details' => $details,
                ]);
            }
        }

        return $result;
    }

    private function createMovement(RecurrenceTemplate $template, RecurrenceOccurrence $occurrence, CarbonImmutable $scheduledOn): Movement
    {
        $movement = Movement::create([
            'project_id' => $template->project_id,
            'type' => $template->type,
            'amount_cents' => $template->amount_cents,
            'occurred_on' => $scheduledOn->toDateString(),
            'concept' => $template->concept,
            'category_id' => $template->category_id,
            'subcategory_id' => $template->subcategory_id,
            'financial_account_id' => $template->financial_account_id,
            'destination_account_id' => $template->destination_account_id,
            'paid_by_user_id' => $template->paid_by_user_id,
            'notes' => $template->notes,
            'recurrence_template_id' => $template->id,
            'recurrence_occurrence_id' => $occurrence->id,
            'generated_automatically_at' => now(),
            'created_by_user_id' => $template->created_by_user_id,
            'updated_by_user_id' => $template->created_by_user_id,
        ]);
        $movement->tags()->sync($template->tags->modelKeys());
        $this->entries->handle($movement);
        if ($template->savingsGoal !== null) {
            $this->goalAllocation->handle($movement, $template->savingsGoal, GoalAllocationDirection::Contribution);
        }
        $this->audit->handle($template->project, null, 'movement', $movement->id, 'generated', null, $movement->auditSnapshot());

        return $movement;
    }

    private function dependenciesAreUsable(RecurrenceTemplate $template): bool
    {
        if ($template->account->archived_at !== null) {
            return false;
        }
        if ($template->destinationAccount !== null && $template->destinationAccount->archived_at !== null) {
            return false;
        }
        if ($template->category !== null && $template->category->archived_at !== null) {
            return false;
        }
        if ($template->subcategory !== null && $template->subcategory->archived_at !== null) {
            return false;
        }
        if ($template->savingsGoal !== null && $template->savingsGoal->archived_at !== null) {
            return false;
        }
        if ($template->paid_by_user_id !== null && ! $template->project->activeMemberships()->where('user_id', $template->paid_by_user_id)->exists()) {
            return false;
        }

        return true;
    }
}
