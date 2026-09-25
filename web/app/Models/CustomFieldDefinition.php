<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'name', 'name_normalized', 'type', 'applicable_movement_types', 'position', 'is_initial',
    'archived_at', 'archived_by_user_id', 'created_by_user_id', 'updated_by_user_id',
])]
class CustomFieldDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'applicable_movement_types' => 'array',
            'is_initial' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function appliesTo(MovementType $type): bool
    {
        return in_array($type->value, $this->applicable_movement_types, true);
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'applicable_movement_types' => $this->applicable_movement_types,
            'position' => $this->position,
            'is_initial' => $this->is_initial,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
