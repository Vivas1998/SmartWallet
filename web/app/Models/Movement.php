<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use App\Support\CustomFieldValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'project_id', 'type', 'amount_cents', 'occurred_on', 'concept', 'category_id', 'subcategory_id',
    'financial_account_id', 'destination_account_id', 'paid_by_user_id', 'original_movement_id', 'notes',
    'recurrence_template_id', 'recurrence_occurrence_id', 'generated_automatically_at',
    'show_in_calendar',
    'trashed_at', 'purge_at', 'created_by_user_id', 'updated_by_user_id', 'deleted_by_user_id', 'restored_by_user_id',
])]
class Movement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'occurred_on' => 'date',
            'trashed_at' => 'datetime',
            'purge_at' => 'datetime',
            'generated_automatically_at' => 'datetime',
            'show_in_calendar' => 'boolean',
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

    public function originalMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_movement_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'original_movement_id');
    }

    public function recurrenceTemplate(): BelongsTo
    {
        return $this->belongsTo(RecurrenceTemplate::class);
    }

    public function recurrenceOccurrence(): BelongsTo
    {
        return $this->belongsTo(RecurrenceOccurrence::class);
    }

    public function goalAllocation(): HasOne
    {
        return $this->hasOne(GoalAllocation::class);
    }

    public function leftoverAllocation(): HasOne
    {
        return $this->hasOne(MonthlyLeftoverAllocation::class);
    }

    public function plannedMovement(): HasOne
    {
        return $this->hasOne(PlannedMovement::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccountEntry::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
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
            'occurred_on' => $this->occurred_on->toDateString(),
            'concept' => $this->concept,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'financial_account_id' => $this->financial_account_id,
            'destination_account_id' => $this->destination_account_id,
            'paid_by_user_id' => $this->paid_by_user_id,
            'original_movement_id' => $this->original_movement_id,
            'recurrence_template_id' => $this->recurrence_template_id,
            'recurrence_occurrence_id' => $this->recurrence_occurrence_id,
            'generated_automatically_at' => $this->generated_automatically_at?->toIso8601String(),
            'show_in_calendar' => $this->show_in_calendar,
            'savings_goal_id' => $this->goalAllocation?->savings_goal_id,
            'goal_direction' => $this->goalAllocation?->direction->value,
            'leftover_budget_month' => $this->leftoverAllocation?->budget_month->toDateString(),
            'tag_ids' => $this->tags()->orderBy('tags.id')->pluck('tags.id')->all(),
            'custom_fields' => app(CustomFieldValues::class)->snapshot($this),
            'notes' => $this->notes,
            'trashed_at' => $this->trashed_at?->toIso8601String(),
            'purge_at' => $this->purge_at?->toIso8601String(),
        ];
    }
}
