<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monthly_budget_id', 'category_id', 'limit_cents'])]
class MonthlyBudgetLimit extends Model
{
    public function budget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class, 'monthly_budget_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
