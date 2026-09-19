<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoalAllocationDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['savings_goal_id', 'movement_id', 'direction'])]
class GoalAllocation extends Model
{
    protected function casts(): array
    {
        return ['direction' => GoalAllocationDirection::class];
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }
}
