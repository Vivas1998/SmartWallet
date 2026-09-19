<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'parent_id',
    'type',
    'name',
    'color',
    'icon',
    'position',
    'is_initial',
    'archived_at',
    'created_by_user_id',
    'updated_by_user_id',
    'archived_by_user_id',
])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'is_initial' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('name');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function isMain(): bool
    {
        return $this->parent_id === null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function iconSymbol(): string
    {
        return match ($this->icon) {
            'home' => '⌂',
            'bolt' => 'ϟ',
            'cart' => '▣',
            'car' => '◆',
            'health' => '+',
            'education' => '▤',
            'family' => '●',
            'paw' => '♧',
            'bag' => '▥',
            'leisure' => '★',
            'travel' => '✦',
            'repeat' => '↻',
            'document' => '▧',
            'bank' => '▦',
            'heart' => '♥',
            'alert' => '!',
            'briefcase' => '▰',
            'aid' => '◇',
            'chart' => '↗',
            'gift' => '✚',
            'plus' => '+',
            default => '•',
        };
    }
}
