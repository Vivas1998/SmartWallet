<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Enums\MovementType;
use App\Models\CustomFieldDefinition;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

final class InitialCustomFieldCatalog
{
    /** @var list<array{name:string,type:CustomFieldType}> */
    private const DEFINITIONS = [
        ['name' => 'Número de factura', 'type' => CustomFieldType::Text],
        ['name' => 'Fecha de garantía', 'type' => CustomFieldType::Date],
        ['name' => 'Gasto deducible', 'type' => CustomFieldType::Boolean],
        ['name' => 'Método de compra', 'type' => CustomFieldType::Text],
        ['name' => 'Cubierto por el seguro', 'type' => CustomFieldType::Boolean],
    ];

    public function createFor(Project $project, User $actor): void
    {
        foreach (self::DEFINITIONS as $position => $definition) {
            CustomFieldDefinition::create([
                'project_id' => $project->id,
                'name' => $definition['name'],
                'name_normalized' => Str::lower($definition['name']),
                'type' => $definition['type'],
                'applicable_movement_types' => [MovementType::Expense->value],
                'position' => $position,
                'is_initial' => true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        }
    }
}
