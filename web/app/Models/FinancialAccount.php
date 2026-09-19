<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialAccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'name',
    'type',
    'initial_balance_cents',
    'initial_balance_date',
    'credit_limit_cents',
    'color',
    'icon',
    'position',
    'archived_at',
    'created_by_user_id',
    'updated_by_user_id',
])]
class FinancialAccount extends Model
{
    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'initial_balance_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccountEntry::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function currentBalanceCents(): int
    {
        $entriesTotal = array_key_exists('entries_total', $this->attributes)
            ? (int) $this->attributes['entries_total']
            : (int) $this->entries()->sum('signed_amount_cents');

        return (int) $this->initial_balance_cents + $entriesTotal;
    }

    public function formattedCurrentBalance(): string
    {
        return number_format($this->currentBalanceCents() / 100, 2, ',', '.').' €';
    }

    public function formattedInitialBalance(): string
    {
        return number_format($this->initial_balance_cents / 100, 2, ',', '.').' €';
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'initial_balance_cents' => $this->initial_balance_cents,
            'initial_balance_date' => $this->initial_balance_date->toDateString(),
            'credit_limit_cents' => $this->credit_limit_cents,
            'color' => $this->color,
            'icon' => $this->icon,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
