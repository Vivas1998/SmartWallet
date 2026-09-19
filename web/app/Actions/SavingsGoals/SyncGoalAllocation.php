<?php

declare(strict_types=1);

namespace App\Actions\SavingsGoals;

use App\Enums\GoalAllocationDirection;
use App\Models\Movement;
use App\Models\SavingsGoal;

final class SyncGoalAllocation
{
    public function handle(Movement $movement, ?SavingsGoal $goal, ?GoalAllocationDirection $direction): void
    {
        if ($goal === null || $direction === null) {
            $movement->goalAllocation()->delete();

            return;
        }

        $movement->goalAllocation()->updateOrCreate([], [
            'savings_goal_id' => $goal->id,
            'direction' => $direction,
        ]);
    }
}
