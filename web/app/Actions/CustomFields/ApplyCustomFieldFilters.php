<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use App\Models\Movement;
use App\Models\Project;
use App\Support\CustomFieldValues;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ApplyCustomFieldFilters
{
    public function __construct(private readonly CustomFieldValues $customFields) {}

    /** @param Builder<Movement> $query */
    public function handle(Request $request, Project $project, Builder $query): void
    {
        $filters = $request->input('custom_filters', []);
        if ($filters === null || $filters === []) {
            return;
        }
        if (! is_array($filters)) {
            throw ValidationException::withMessages(['custom_filters' => 'Los filtros personalizados no son válidos.']);
        }

        $definitions = $project->customFieldDefinitions()
            ->whereKey(array_map('intval', array_keys($filters)))
            ->get()
            ->keyBy('id');
        if ($definitions->count() !== count(array_unique(array_map('intval', array_keys($filters))))) {
            throw ValidationException::withMessages(['custom_filters' => 'Alguno de los filtros no pertenece a este proyecto.']);
        }

        foreach ($filters as $definitionId => $filter) {
            $definition = $definitions->get((int) $definitionId);
            if (! $definition instanceof CustomFieldDefinition || ! is_array($filter)) {
                throw ValidationException::withMessages(['custom_filters' => 'Los filtros personalizados no son válidos.']);
            }

            match ($definition->type) {
                CustomFieldType::Text => $this->text($query, $definition, $filter),
                CustomFieldType::Number => $this->number($query, $definition, $filter),
                CustomFieldType::Date => $this->date($query, $definition, $filter),
                CustomFieldType::Boolean => $this->boolean($query, $definition, $filter),
            };
        }
    }

    /** @param Builder<Movement> $query @param array<string, mixed> $filter */
    private function text(Builder $query, CustomFieldDefinition $definition, array $filter): void
    {
        $value = trim((string) ($filter['value'] ?? ''));
        if ($value === '') {
            return;
        }
        if (mb_strlen($value) > 255) {
            throw ValidationException::withMessages(['custom_filters.'.$definition->id.'.value' => 'El filtro no puede superar 255 caracteres.']);
        }
        $escapedValue = addcslashes($value, '\\%_');
        $query->whereHas('customFieldValues', fn ($values) => $values
            ->where('custom_field_definition_id', $definition->id)
            ->where('value_text', 'like', '%'.$escapedValue.'%'));
    }

    /** @param Builder<Movement> $query @param array<string, mixed> $filter */
    private function number(Builder $query, CustomFieldDefinition $definition, array $filter): void
    {
        $minimum = $this->customFields->normalizeNumber($filter['min'] ?? null, 'custom_filters.'.$definition->id.'.min');
        $maximum = $this->customFields->normalizeNumber($filter['max'] ?? null, 'custom_filters.'.$definition->id.'.max');
        if ($minimum === null && $maximum === null) {
            return;
        }
        if ($minimum !== null && $maximum !== null && (float) $minimum > (float) $maximum) {
            throw ValidationException::withMessages(['custom_filters.'.$definition->id.'.max' => 'El máximo debe ser igual o superior al mínimo.']);
        }
        $query->whereHas('customFieldValues', fn ($values) => $values
            ->where('custom_field_definition_id', $definition->id)
            ->when($minimum !== null, fn ($builder) => $builder->where('value_number', '>=', $minimum))
            ->when($maximum !== null, fn ($builder) => $builder->where('value_number', '<=', $maximum)));
    }

    /** @param Builder<Movement> $query @param array<string, mixed> $filter */
    private function date(Builder $query, CustomFieldDefinition $definition, array $filter): void
    {
        $from = $this->validDate($filter['from'] ?? null, 'custom_filters.'.$definition->id.'.from');
        $to = $this->validDate($filter['to'] ?? null, 'custom_filters.'.$definition->id.'.to');
        if ($from === null && $to === null) {
            return;
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages(['custom_filters.'.$definition->id.'.to' => 'La fecha final debe ser igual o posterior a la inicial.']);
        }
        $query->whereHas('customFieldValues', fn ($values) => $values
            ->where('custom_field_definition_id', $definition->id)
            ->when($from !== null, fn ($builder) => $builder->whereDate('value_date', '>=', $from))
            ->when($to !== null, fn ($builder) => $builder->whereDate('value_date', '<=', $to)));
    }

    /** @param Builder<Movement> $query @param array<string, mixed> $filter */
    private function boolean(Builder $query, CustomFieldDefinition $definition, array $filter): void
    {
        $value = (string) ($filter['value'] ?? '');
        if ($value === '') {
            return;
        }
        if (! in_array($value, ['0', '1'], true)) {
            throw ValidationException::withMessages(['custom_filters.'.$definition->id.'.value' => 'Selecciona Sí, No o cualquier valor.']);
        }
        $query->whereHas('customFieldValues', fn ($values) => $values
            ->where('custom_field_definition_id', $definition->id)
            ->where('value_boolean', $value === '1'));
    }

    private function validDate(mixed $value, string $field): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
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
}
