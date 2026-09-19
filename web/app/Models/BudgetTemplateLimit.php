<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_template_id', 'category_id', 'limit_cents'])]
class BudgetTemplateLimit extends Model
{
    public function template(): BelongsTo
    {
        return $this->belongsTo(BudgetTemplate::class, 'budget_template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
