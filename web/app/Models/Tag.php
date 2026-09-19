<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'project_id', 'name', 'name_normalized', 'archived_at', 'merged_into_tag_id',
    'created_by_user_id', 'updated_by_user_id',
])]
class Tag extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            $tag->name = Str::of((string) $tag->name)->squish()->toString();
            $tag->name_normalized = Str::of($tag->name)->lower()->toString();
        });
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function movements(): BelongsToMany
    {
        return $this->belongsToMany(Movement::class)->withTimestamps();
    }

    public function recurrenceTemplates(): BelongsToMany
    {
        return $this->belongsToMany(RecurrenceTemplate::class)->withTimestamps();
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_tag_id');
    }

    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'merged_into_tag_id' => $this->merged_into_tag_id,
        ];
    }
}
