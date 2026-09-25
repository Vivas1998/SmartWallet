<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'custom_field_definition_id', 'movement_id', 'planned_movement_id', 'recurrence_template_id',
    'value_text', 'value_number', 'value_date', 'value_boolean',
])]
class CustomFieldValue extends Model
{
    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'date',
            'value_boolean' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function plannedMovement(): BelongsTo
    {
        return $this->belongsTo(PlannedMovement::class);
    }

    public function recurrenceTemplate(): BelongsTo
    {
        return $this->belongsTo(RecurrenceTemplate::class);
    }

    public function rawValue(): string|bool|null
    {
        return match ($this->definition->type) {
            CustomFieldType::Text => $this->value_text,
            CustomFieldType::Number => $this->value_number,
            CustomFieldType::Date => $this->value_date?->toDateString(),
            CustomFieldType::Boolean => $this->value_boolean,
        };
    }

    public function displayValue(): string
    {
        return match ($this->definition->type) {
            CustomFieldType::Text => (string) $this->value_text,
            CustomFieldType::Number => rtrim(rtrim(number_format((float) $this->value_number, 6, ',', ''), '0'), ','),
            CustomFieldType::Date => $this->value_date?->format('d/m/Y') ?? '',
            CustomFieldType::Boolean => $this->value_boolean ? 'Sí' : 'No',
        };
    }

    /** @return array<string, string|bool|null> */
    public function auditValue(): array
    {
        return [
            'name' => $this->definition->name,
            'type' => $this->definition->type->value,
            'value' => $this->rawValue(),
        ];
    }
}
