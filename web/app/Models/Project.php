<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'creator_user_id',
    'name',
    'description',
    'color',
    'icon',
    'currency',
    'locale',
    'timezone',
    'archived_at',
    'archived_by_user_id',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->whereNull('removed_at');
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->wherePivotNull('removed_at')
            ->withPivot(['role', 'joined_at', 'removed_at', 'added_by_user_id'])
            ->withTimestamps();
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function plannedMovements(): HasMany
    {
        return $this->hasMany(PlannedMovement::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function latestAuditLog(): HasOne
    {
        return $this->hasOne(AuditLog::class)->latestOfMany();
    }

    public function monthlyBudgets(): HasMany
    {
        return $this->hasMany(MonthlyBudget::class);
    }

    public function budgetTemplates(): HasMany
    {
        return $this->hasMany(BudgetTemplate::class);
    }

    public function leftoverAllocations(): HasMany
    {
        return $this->hasMany(MonthlyLeftoverAllocation::class);
    }

    public function recurrenceTemplates(): HasMany
    {
        return $this->hasMany(RecurrenceTemplate::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function recoveryNotices(): HasMany
    {
        return $this->hasMany(RecurrenceRecoveryNotice::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'archived_by_user_id' => $this->archived_by_user_id,
        ];
    }

    public function iconSymbol(): string
    {
        return match ($this->icon) {
            'wallet' => '◫',
            'personal' => '●',
            'travel' => '✦',
            'heart' => '♥',
            default => '⌂',
        };
    }
}
