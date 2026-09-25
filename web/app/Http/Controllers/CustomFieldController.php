<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Enums\CustomFieldType;
use App\Enums\MovementType;
use App\Models\CustomFieldDefinition;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('custom-fields.index', [
            'project' => $project,
            'definitions' => $project->customFieldDefinitions()
                ->withCount('values')
                ->orderByRaw('archived_at is not null')
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
            'fieldTypes' => CustomFieldType::cases(),
            'movementTypes' => MovementType::cases(),
            'canManage' => $request->user()->can('manageCustomFields', $project),
            'activeCount' => $project->customFieldDefinitions()->whereNull('archived_at')->count(),
        ]);
    }

    public function store(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageCustomFields', $project);
        if ($project->customFieldDefinitions()->whereNull('archived_at')->count() >= 10) {
            throw ValidationException::withMessages([
                'name' => 'El proyecto ya tiene el máximo de diez campos personalizados activos.',
            ]);
        }
        $data = $this->validatedData($request, $project);

        DB::transaction(function () use ($request, $project, $data, $audit): void {
            $definition = CustomFieldDefinition::create([
                'project_id' => $project->id,
                ...$data,
                'position' => (int) $project->customFieldDefinitions()->max('position') + 1,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'custom_field', $definition->id, 'created', null, $definition->auditSnapshot());
        });

        return back()->with('status', 'Campo personalizado creado. Ya está disponible en los tipos de movimiento seleccionados.');
    }

    public function update(
        Request $request,
        Project $project,
        CustomFieldDefinition $customField,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageCustomFields', $project);
        $this->ensureDefinition($project, $customField);
        if ($customField->isArchived()) {
            throw ValidationException::withMessages([
                'custom_field' => 'Reactiva el campo antes de modificar su configuración.',
            ]);
        }
        $data = $this->validatedData($request, $project, $customField);
        if ($customField->values()->exists() && $data['type'] !== $customField->type->value) {
            throw ValidationException::withMessages([
                'type' => 'No se puede cambiar el tipo de un campo que ya contiene valores.',
            ]);
        }

        DB::transaction(function () use ($request, $project, $customField, $data, $audit): void {
            $before = $customField->auditSnapshot();
            $customField->update([...$data, 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'custom_field', $customField->id, 'updated', $before, $customField->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Campo personalizado actualizado. Los valores existentes se conservan.');
    }

    public function archive(
        Request $request,
        Project $project,
        CustomFieldDefinition $customField,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageCustomFields', $project);
        $this->ensureDefinition($project, $customField);
        if ($customField->isArchived()) {
            return back();
        }

        $before = $customField->auditSnapshot();
        $customField->update([
            'archived_at' => now(),
            'archived_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        $audit->handle($project, $request->user(), 'custom_field', $customField->id, 'archived', $before, $customField->fresh()->auditSnapshot());

        return back()->with('status', 'Campo archivado. Sus valores históricos siguen disponibles.');
    }

    public function restore(
        Request $request,
        Project $project,
        CustomFieldDefinition $customField,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageCustomFields', $project);
        $this->ensureDefinition($project, $customField);
        if (! $customField->isArchived()) {
            return back();
        }
        if ($project->customFieldDefinitions()->whereNull('archived_at')->count() >= 10) {
            throw ValidationException::withMessages([
                'custom_field' => 'Archiva otro campo antes de reactivar este: el máximo es diez campos activos.',
            ]);
        }

        $before = $customField->auditSnapshot();
        $customField->update([
            'archived_at' => null,
            'archived_by_user_id' => null,
            'updated_by_user_id' => $request->user()->id,
        ]);
        $audit->handle($project, $request->user(), 'custom_field', $customField->id, 'reactivated', $before, $customField->fresh()->auditSnapshot());

        return back()->with('status', 'Campo personalizado reactivado.');
    }

    public function destroy(
        Request $request,
        Project $project,
        CustomFieldDefinition $customField,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageCustomFields', $project);
        $this->ensureDefinition($project, $customField);
        if ($customField->values()->exists()) {
            throw ValidationException::withMessages([
                'custom_field' => 'Un campo utilizado no se puede eliminar; archívalo para conservar su historial.',
            ]);
        }

        DB::transaction(function () use ($request, $project, $customField, $audit): void {
            $snapshot = $customField->auditSnapshot();
            $id = $customField->id;
            $customField->delete();
            $audit->handle($project, $request->user(), 'custom_field', $id, 'purged', $snapshot, null);
        });

        return back()->with('status', 'Campo vacío eliminado definitivamente.');
    }

    public function move(
        Request $request,
        Project $project,
        CustomFieldDefinition $customField,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageCustomFields', $project);
        $this->ensureDefinition($project, $customField);
        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $operator = $validated['direction'] === 'up' ? '<' : '>';
        $order = $validated['direction'] === 'up' ? 'desc' : 'asc';
        $neighbour = $project->customFieldDefinitions()
            ->whereNull('archived_at')
            ->where('position', $operator, $customField->position)
            ->orderBy('position', $order)
            ->first();
        if ($neighbour === null || $customField->isArchived()) {
            return back();
        }

        DB::transaction(function () use ($request, $project, $customField, $neighbour, $audit): void {
            $before = $customField->auditSnapshot();
            $position = $customField->position;
            $customField->update(['position' => $neighbour->position, 'updated_by_user_id' => $request->user()->id]);
            $neighbour->update(['position' => $position, 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'custom_field', $customField->id, 'updated', $before, $customField->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Orden de campos actualizado.');
    }

    /** @return array{name:string,name_normalized:string,type:string,applicable_movement_types:list<string>} */
    private function validatedData(
        Request $request,
        Project $project,
        ?CustomFieldDefinition $current = null,
    ): array {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'applicable_movement_types' => ['required', 'array', 'min:1'],
            'applicable_movement_types.*' => ['required', 'distinct', Rule::enum(MovementType::class)],
        ]);
        $name = trim($validated['name']);
        $normalized = Str::lower($name);
        $duplicate = $project->customFieldDefinitions()
            ->where('name_normalized', $normalized)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ya existe un campo con este nombre en el proyecto.']);
        }

        return [
            'name' => $name,
            'name_normalized' => $normalized,
            'type' => $validated['type'],
            'applicable_movement_types' => array_values($validated['applicable_movement_types']),
        ];
    }

    private function ensureDefinition(Project $project, CustomFieldDefinition $customField): void
    {
        abort_unless($customField->project_id === $project->id, 404);
    }
}
