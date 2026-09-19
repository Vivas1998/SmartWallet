<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'type', 'amount_cents', 'concept', 'category_id', 'subcategory_id', 'financial_account_id',
    'destination_account_id', 'paid_by_user_id', 'savings_goal_id', 'notes', 'frequency', 'start_on', 'anchor_day',
    'next_occurrence_on', 'ends_on', 'paused_at', 'created_by_user_id', 'updated_by_user_id',
])]
class RecurrenceTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'frequency' => RecurrenceFrequency::class,
            'start_on' => 'date',
            'next_occurrence_on' => 'date',
            'ends_on' => 'date',
            'paused_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'destination_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurrenceOccurrence::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function formattedAmount(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.').' €';
    }

    public function auditSnapshot(): array
    {
        return [
            'type' => $this->type->value,
            'amount_cents' => $this->amount_cents,
            'concept' => $this->concept,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'financial_account_id' => $this->financial_account_id,
            'destination_account_id' => $this->destination_account_id,
            'paid_by_user_id' => $this->paid_by_user_id,
            'savings_goal_id' => $this->savings_goal_id,
            'frequency' => $this->frequency->value,
            'start_on' => $this->start_on->toDateString(),
            'next_occurrence_on' => $this->next_occurrence_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'tag_ids' => $this->tags()->orderBy('tags.id')->pluck('tags.id')->all(),
        ];
    }
}
