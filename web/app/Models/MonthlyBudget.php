<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'month', 'total_limit_cents', 'source_template_id', 'created_by_user_id', 'updated_by_user_id'])]
class MonthlyBudget extends Model
{
    protected function casts(): array
    {
        return ['month' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(MonthlyBudgetLimit::class);
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(BudgetTemplate::class, 'source_template_id');
    }
}
