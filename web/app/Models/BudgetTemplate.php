<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'effective_from_month', 'total_limit_cents', 'created_by_user_id', 'updated_by_user_id'])]
class BudgetTemplate extends Model
{
    protected function casts(): array
    {
        return ['effective_from_month' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(BudgetTemplateLimit::class);
    }
}
