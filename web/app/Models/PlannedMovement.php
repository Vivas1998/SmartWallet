<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\PlannedMovementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'project_id', 'type', 'amount_cents', 'due_on', 'concept', 'category_id', 'subcategory_id',
    'financial_account_id', 'destination_account_id', 'paid_by_user_id', 'savings_goal_id', 'notes',
    'status', 'movement_id', 'completed_at', 'cancelled_at', 'cancelled_by_user_id',
    'created_by_user_id', 'updated_by_user_id',
])]
class PlannedMovement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'due_on' => 'date',
            'status' => PlannedMovementStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'destination_account_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function formattedAmount(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.').' €';
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'type' => $this->type->value,
            'amount_cents' => $this->amount_cents,
            'due_on' => $this->due_on->toDateString(),
            'concept' => $this->concept,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'financial_account_id' => $this->financial_account_id,
            'destination_account_id' => $this->destination_account_id,
            'paid_by_user_id' => $this->paid_by_user_id,
            'savings_goal_id' => $this->savings_goal_id,
            'status' => $this->status->value,
            'movement_id' => $this->movement_id,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_user_id' => $this->cancelled_by_user_id,
            'tag_ids' => $this->tags()->orderBy('tags.id')->pluck('tags.id')->all(),
            'notes' => $this->notes,
        ];
    }
}
