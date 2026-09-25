<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Enums\MovementType;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\RecurrenceTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CustomFieldValues
{
    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public function definitionsForForm(
        Project $project,
        Movement|PlannedMovement|RecurrenceTemplate|null $current = null,
    ): Collection {
        $usedDefinitionIds = $current?->customFieldValues()->pluck('custom_field_definition_id')->all() ?? [];

        return $project->customFieldDefinitions()
            ->where(fn ($query) => $query->whereNull('archived_at')->when(
                $usedDefinitionIds !== [],
                fn ($definitions) => $definitions->orWhereIn('id', $usedDefinitionIds),
            ))
            ->orderByRaw('archived_at is not null')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array{value_text:?string,value_number:?string,value_date:?string,value_boolean:?bool}|null>
     */
    public function validate(Request $request, Project $project, MovementType $movementType): array
    {
        $submitted = $request->input('custom_fields', []);
        if (! is_array($submitted)) {
            throw ValidationException::withMessages([
                'custom_fields' => 'Los valores de información adicional no son válidos.',
            ]);
        }

        $definitions = $project->customFieldDefinitions()
            ->whereNull('archived_at')
            ->orderBy('position')
            ->get()
            ->filter(fn (CustomFieldDefinition $definition): bool => $definition->appliesTo($movementType));
        $allowedIds = $definitions->modelKeys();
        $submittedIds = array_map('intval', array_keys($submitted));

        if (array_diff($submittedIds, $allowedIds) !== []) {
            throw ValidationException::withMessages([
                'custom_fields' => 'Solo puedes rellenar campos activos y aplicables a este tipo de movimiento.',
            ]);
        }

        $values = [];
        foreach ($definitions as $definition) {
            $raw = $submitted[$definition->id] ?? null;
            $values[$definition->id] = $this->normalizeValue($definition, $raw);
        }

        return $values;
    }

    /**
     * @param  array<int, array{value_text:?string,value_number:?string,value_date:?string,value_boolean:?bool}|null>  $values
     */
    public function sync(
        Movement|PlannedMovement|RecurrenceTemplate $subject,
        array $values,
    ): void {
        $submittedDefinitionIds = array_keys($values);
        if ($submittedDefinitionIds !== []) {
            $subject->customFieldValues()
                ->whereIn('custom_field_definition_id', $submittedDefinitionIds)
                ->delete();
        }

        $column = $this->subjectColumn($subject);
        foreach ($values as $definitionId => $value) {
            if ($value === null) {
                continue;
            }

            CustomFieldValue::create([
                'custom_field_definition_id' => $definitionId,
                $column => $subject->id,
                ...$value,
            ]);
        }

        $subject->unsetRelation('customFieldValues');
    }

    public function copy(
        Movement|PlannedMovement|RecurrenceTemplate $source,
        Movement|PlannedMovement|RecurrenceTemplate $target,
    ): void {
        $source->loadMissing('customFieldValues');
        $column = $this->subjectColumn($target);

        foreach ($source->customFieldValues as $value) {
            CustomFieldValue::create([
                'custom_field_definition_id' => $value->custom_field_definition_id,
                $column => $target->id,
                'value_text' => $value->value_text,
                'value_number' => $value->value_number,
                'value_date' => $value->value_date?->toDateString(),
                'value_boolean' => $value->value_boolean,
            ]);
        }

        $target->unsetRelation('customFieldValues');
    }

    /** @return array<string, array<string, string|bool|null>> */
    public function snapshot(Movement|PlannedMovement|RecurrenceTemplate $subject): array
    {
        return $subject->customFieldValues()
            ->with('definition')
            ->get()
            ->sortBy(fn (CustomFieldValue $value): array => [$value->definition->position, $value->definition->name])
            ->mapWithKeys(fn (CustomFieldValue $value): array => [
                (string) $value->custom_field_definition_id => $value->auditValue(),
            ])
            ->all();
    }

    public function normalizeNumber(mixed $value, string $field): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^-?\d+(?:[,.]\d{1,6})?$/', $raw) !== 1) {
            throw ValidationException::withMessages([
                $field => 'Introduce un número válido con un máximo de seis decimales.',
            ]);
        }

        $normalized = str_replace(',', '.', $raw);
        [$integer, $decimal] = array_pad(explode('.', $normalized, 2), 2, null);
        $integer = ltrim($integer, '0');
        if ($integer === '' || $integer === '-') {
            $integer .= '0';
        }
        if (strlen(ltrim($integer, '-')) > 14) {
            throw ValidationException::withMessages([
                $field => 'El número es demasiado grande.',
            ]);
        }

        return $decimal === null ? $integer : $integer.'.'.$decimal;
    }

    /** @return array{value_text:?string,value_number:?string,value_date:?string,value_boolean:?bool}|null */
    private function normalizeValue(CustomFieldDefinition $definition, mixed $value): ?array
    {
        $field = 'custom_fields.'.$definition->id;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            throw ValidationException::withMessages([$field => 'El valor indicado no es válido.']);
        }

        $columns = [
            'value_text' => null,
            'value_number' => null,
            'value_date' => null,
            'value_boolean' => null,
        ];

        match ($definition->type) {
            CustomFieldType::Text => $columns['value_text'] = $this->text($value, $field),
            CustomFieldType::Number => $columns['value_number'] = $this->normalizeNumber($value, $field),
            CustomFieldType::Date => $columns['value_date'] = $this->date($value, $field),
            CustomFieldType::Boolean => $columns['value_boolean'] = $this->boolean($value, $field),
        };

        return $columns;
    }

    private function text(mixed $value, string $field): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) > 255) {
            throw ValidationException::withMessages([$field => 'El texto no puede superar 255 caracteres.']);
        }

        return $text;
    }

    private function date(mixed $value, string $field): string
    {
        $raw = (string) $value;
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw, 'Europe/Madrid');
        } catch (\Throwable) {
            $date = null;
        }
        if ($date === null || $date->format('Y-m-d') !== $raw) {
            throw ValidationException::withMessages([$field => 'Introduce una fecha válida.']);
        }

        return $raw;
    }

    private function boolean(mixed $value, string $field): bool
    {
        if (! in_array((string) $value, ['0', '1'], true)) {
            throw ValidationException::withMessages([$field => 'Selecciona Sí, No o deja el campo vacío.']);
        }

        return (string) $value === '1';
    }

    private function subjectColumn(Movement|PlannedMovement|RecurrenceTemplate $subject): string
    {
        return match (true) {
            $subject instanceof Movement => 'movement_id',
            $subject instanceof PlannedMovement => 'planned_movement_id',
            $subject instanceof RecurrenceTemplate => 'recurrence_template_id',
        };
    }
}
