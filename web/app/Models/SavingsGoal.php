<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoalAllocationDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'financial_account_id', 'name', 'target_amount_cents', 'target_date', 'archived_at',
    'created_by_user_id', 'updated_by_user_id',
])]
class SavingsGoal extends Model
{
    protected function casts(): array
    {
        return ['target_date' => 'date', 'archived_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(GoalAllocation::class);
    }

    public function currentAmountCents(): int
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations
            : $this->allocations()->with('movement')->get();

        return (int) $allocations
            ->filter(fn (GoalAllocation $allocation): bool => $allocation->movement !== null && $allocation->movement->trashed_at === null)
            ->sum(fn (GoalAllocation $allocation): int => ($allocation->direction === GoalAllocationDirection::Contribution ? 1 : -1) * $allocation->movement->amount_cents);
    }

    public function progressPercentage(): int
    {
        return $this->target_amount_cents > 0
            ? max(0, (int) round(($this->currentAmountCents() / $this->target_amount_cents) * 100))
            : 0;
    }

    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'target_amount_cents' => $this->target_amount_cents,
            'target_date' => $this->target_date?->toDateString(),
            'financial_account_id' => $this->financial_account_id,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
