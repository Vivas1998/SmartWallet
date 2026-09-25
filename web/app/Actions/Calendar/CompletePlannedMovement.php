<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Movements\RebuildMovementEntries;
use App\Actions\SavingsGoals\SyncGoalAllocation;
use App\Enums\GoalAllocationDirection;
use App\Enums\PlannedMovementStatus;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\CustomFieldValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompletePlannedMovement
{
    public function __construct(
        private readonly RebuildMovementEntries $entries,
        private readonly SyncGoalAllocation $goalAllocation,
        private readonly RecordProjectAudit $audit,
        private readonly CustomFieldValues $customFields,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $tagIds
     * @param  array<int, array{value_text:?string,value_number:?string,value_date:?string,value_boolean:?bool}|null>  $customValues
     */
    public function handle(
        PlannedMovement $plannedMovement,
        User $actor,
        array $attributes,
        array $tagIds,
        ?SavingsGoal $goal,
        array $customValues,
    ): Movement {
        return DB::transaction(function () use ($plannedMovement, $actor, $attributes, $tagIds, $goal, $customValues): Movement {
            $plan = PlannedMovement::query()
                ->with(['project', 'tags'])
                ->lockForUpdate()
                ->findOrFail($plannedMovement->id);

            if ($plan->status !== PlannedMovementStatus::Pending) {
                throw ValidationException::withMessages([
                    'planned_movement' => 'Esta planificación ya no está pendiente.',
                ]);
            }

            $before = $plan->auditSnapshot();
            $movement = Movement::create([
                'project_id' => $plan->project_id,
                'type' => $attributes['type'],
                'amount_cents' => $attributes['amount_cents'],
                'occurred_on' => $attributes['occurred_on'],
                'concept' => $attributes['concept'],
                'category_id' => $attributes['category_id'],
                'subcategory_id' => $attributes['subcategory_id'],
                'financial_account_id' => $attributes['financial_account_id'],
                'destination_account_id' => $attributes['destination_account_id'],
                'paid_by_user_id' => $attributes['paid_by_user_id'],
                'notes' => $attributes['notes'],
                'show_in_calendar' => false,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
            $movement->tags()->sync($tagIds);
            $this->customFields->copy($plan, $movement);
            $this->customFields->sync($movement, $customValues);
            $this->entries->handle($movement);
            $this->goalAllocation->handle(
                $movement,
                $goal,
                $goal === null ? null : GoalAllocationDirection::Contribution,
            );

            $plan->update([
                'status' => PlannedMovementStatus::Completed,
                'movement_id' => $movement->id,
                'completed_at' => now(),
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->handle(
                $plan->project,
                $actor,
                'movement',
                $movement->id,
                'created',
                null,
                $movement->fresh()->auditSnapshot(),
            );
            $this->audit->handle(
                $plan->project,
                $actor,
                'planned_movement',
                $plan->id,
                'completed',
                $before,
                $plan->fresh()->auditSnapshot(),
            );

            return $movement->fresh();
        }, 3);
    }
}
