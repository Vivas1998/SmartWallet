<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $categories = $project->categories()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('type')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Category $category): string => $category->type->value);

        return view('categories.index', [
            'project' => $project,
            'categories' => $categories,
            'categoryTypes' => CategoryType::cases(),
            'iconOptions' => $this->iconOptions(),
            'canManage' => $request->user()->can('manageCategories', $project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageCategories', $project);

        $validated = $request->validate($this->rules());
        $type = CategoryType::from($validated['type']);
        $parent = $this->validatedParent($project, $validated['parent_id'] ?? null, $type);
        $name = trim($validated['name']);

        $this->ensureUniqueName($project, $name, $type, $parent?->id);

        $position = ((int) $project->categories()
            ->where('type', $type->value)
            ->where('parent_id', $parent?->id)
            ->max('position')) + 10;

        Category::create([
            'project_id' => $project->id,
            'parent_id' => $parent?->id,
            'type' => $type,
            'name' => $name,
            'color' => $validated['color'],
            'icon' => $validated['icon'],
            'position' => $position,
            'is_initial' => false,
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('categories.index', $project)
            ->with('status', $parent === null ? 'Categoría creada correctamente.' : 'Subcategoría creada correctamente.');
    }

    public function edit(Project $project, Category $category): View
    {
        $this->authorize('manageCategories', $project);
        $this->ensureCategoryBelongsToProject($project, $category);

        $category->load('parent');

        return view('categories.edit', [
            'project' => $project,
            'category' => $category,
            'iconOptions' => $this->iconOptions(),
        ]);
    }

    public function update(Request $request, Project $project, Category $category): RedirectResponse
    {
        $this->authorize('manageCategories', $project);
        $this->ensureCategoryBelongsToProject($project, $category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', Rule::in(array_keys($this->iconOptions()))],
        ]);
        $name = trim($validated['name']);

        $this->ensureUniqueName($project, $name, $category->type, $category->parent_id, $category->id);

        $category->update([
            'name' => $name,
            'color' => $validated['color'],
            'icon' => $validated['icon'],
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('categories.index', $project)
            ->with('status', 'Categoría actualizada correctamente.');
    }

    public function archive(Request $request, Project $project, Category $category): RedirectResponse
    {
        $this->authorize('manageCategories', $project);
        $this->ensureCategoryBelongsToProject($project, $category);

        if (! $category->isArchived()) {
            $category->update([
                'archived_at' => now(),
                'archived_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('categories.index', $project)
            ->with('status', 'Categoría archivada. Su información histórica se conservará.');
    }

    public function restore(Request $request, Project $project, Category $category): RedirectResponse
    {
        $this->authorize('manageCategories', $project);
        $this->ensureCategoryBelongsToProject($project, $category);

        if ($category->parent_id !== null) {
            $parent = $project->categories()->findOrFail($category->parent_id);

            if ($parent->isArchived()) {
                throw ValidationException::withMessages([
                    'category' => 'Reactiva primero la categoría principal.',
                ]);
            }
        }

        $category->update([
            'archived_at' => null,
            'archived_by_user_id' => null,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('categories.index', $project)
            ->with('status', 'Categoría reactivada correctamente.');
    }

    public function move(Request $request, Project $project, Category $category): RedirectResponse
    {
        $this->authorize('manageCategories', $project);
        $this->ensureCategoryBelongsToProject($project, $category);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        if ($category->isArchived()) {
            throw ValidationException::withMessages([
                'category' => 'Reactiva la categoría antes de cambiar su posición.',
            ]);
        }

        DB::transaction(function () use ($category, $project, $validated): void {
            $siblings = $project->categories()
                ->where('type', $category->type->value)
                ->where('parent_id', $category->parent_id)
                ->whereNull('archived_at')
                ->orderBy('position')
                ->orderBy('name')
                ->lockForUpdate()
                ->get();
            $currentIndex = $siblings->search(fn (Category $sibling): bool => $sibling->id === $category->id);
            $targetIndex = $validated['direction'] === 'up'
                ? $currentIndex - 1
                : $currentIndex + 1;

            if ($currentIndex === false || ! $siblings->has($targetIndex)) {
                return;
            }

            $target = $siblings->get($targetIndex);
            $currentPosition = $category->position;

            $category->update(['position' => $target->position]);
            $target->update(['position' => $currentPosition]);
        });

        return redirect()
            ->route('categories.index', $project)
            ->with('status', 'Orden actualizado correctamente.');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'parent_id' => ['nullable', 'integer'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', Rule::in(array_keys($this->iconOptions()))],
        ];
    }

    private function validatedParent(Project $project, mixed $parentId, CategoryType $type): ?Category
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        $parent = $project->categories()->find($parentId);

        if ($parent === null || ! $parent->isMain() || $parent->isArchived() || $parent->type !== $type) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selecciona una categoría principal activa del mismo tipo.',
            ]);
        }

        return $parent;
    }

    private function ensureUniqueName(
        Project $project,
        string $name,
        CategoryType $type,
        ?int $parentId,
        ?int $ignoreId = null,
    ): void {
        $duplicateExists = $project->categories()
            ->where('type', $type->value)
            ->where('parent_id', $parentId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una categoría con ese nombre en el mismo nivel.',
            ]);
        }
    }

    private function ensureCategoryBelongsToProject(Project $project, Category $category): void
    {
        abort_unless($category->project_id === $project->id, 404);
    }

    /**
     * @return array<string, string>
     */
    private function iconOptions(): array
    {
        return [
            'dot' => 'Punto',
            'home' => 'Casa',
            'bolt' => 'Suministros',
            'cart' => 'Compra',
            'car' => 'Transporte',
            'health' => 'Salud',
            'education' => 'Educación',
            'family' => 'Familia',
            'paw' => 'Mascotas',
            'bag' => 'Bolsa',
            'leisure' => 'Ocio',
            'travel' => 'Viaje',
            'repeat' => 'Suscripción',
            'document' => 'Documento',
            'bank' => 'Finanzas',
            'heart' => 'Corazón',
            'alert' => 'Aviso',
            'briefcase' => 'Trabajo',
            'aid' => 'Ayuda',
            'chart' => 'Rendimiento',
            'gift' => 'Regalo',
            'plus' => 'Otros',
        ];
    }
}
