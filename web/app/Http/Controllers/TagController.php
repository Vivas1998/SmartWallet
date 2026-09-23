<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('tags.index', [
            'project' => $project,
            'tags' => $project->tags()->with('mergedInto')->withCount(['movements', 'plannedMovements', 'recurrenceTemplates'])
                ->orderByRaw('archived_at is not null')->orderBy('name')->get(),
            'activeTags' => $project->tags()->whereNull('archived_at')->orderBy('name')->get(),
            'canCreate' => $request->user()->can('createTags', $project),
            'canManage' => $request->user()->can('manageTags', $project),
        ]);
    }

    public function store(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('createTags', $project);
        $name = $this->validatedName($request, $project);

        DB::transaction(function () use ($request, $project, $name, $audit): void {
            $tag = Tag::create([
                'project_id' => $project->id,
                'name' => $name,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'tag', $tag->id, 'created', null, $tag->auditSnapshot());
        });

        return back()->with('status', 'Etiqueta creada y disponible para todos los movimientos del proyecto.');
    }

    public function update(Request $request, Project $project, Tag $tag, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageTags', $project);
        $this->ensureTag($project, $tag);
        $name = $this->validatedName($request, $project, $tag);

        DB::transaction(function () use ($request, $project, $tag, $name, $audit): void {
            $before = $tag->auditSnapshot();
            $tag->update(['name' => $name, 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'tag', $tag->id, 'updated', $before, $tag->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Etiqueta renombrada en todos sus movimientos.');
    }

    public function archive(Request $request, Project $project, Tag $tag, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageTags', $project);
        $this->ensureTag($project, $tag);
        if ($tag->archived_at !== null) {
            return back();
        }

        $before = $tag->auditSnapshot();
        $tag->update(['archived_at' => now(), 'updated_by_user_id' => $request->user()->id]);
        $audit->handle($project, $request->user(), 'tag', $tag->id, 'archived', $before, $tag->fresh()->auditSnapshot());

        return back()->with('status', 'Etiqueta archivada. Se conserva en los movimientos que ya la utilizaban.');
    }

    public function restore(Request $request, Project $project, Tag $tag, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageTags', $project);
        $this->ensureTag($project, $tag);
        if ($tag->archived_at === null) {
            return back();
        }
        if ($tag->merged_into_tag_id !== null) {
            throw ValidationException::withMessages(['tag' => 'Una etiqueta fusionada no puede reactivarse; sus relaciones pertenecen ahora a la etiqueta de destino.']);
        }

        $before = $tag->auditSnapshot();
        $tag->update(['archived_at' => null, 'updated_by_user_id' => $request->user()->id]);
        $audit->handle($project, $request->user(), 'tag', $tag->id, 'restored', $before, $tag->fresh()->auditSnapshot());

        return back()->with('status', 'Etiqueta reactivada.');
    }

    public function merge(Request $request, Project $project, Tag $tag, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageTags', $project);
        $this->ensureTag($project, $tag);
        $validated = $request->validate(['target_tag_id' => ['required', 'integer', 'different:tag']]);
        $target = $project->tags()->whereKey((int) $validated['target_tag_id'])->whereNull('archived_at')->first();
        if ($target === null || $target->is($tag) || $tag->archived_at !== null) {
            throw ValidationException::withMessages(['target_tag_id' => 'Selecciona otra etiqueta activa del proyecto.']);
        }

        DB::transaction(function () use ($request, $project, $tag, $target, $audit): void {
            $before = $tag->auditSnapshot();
            $this->movePivotRelations('movement_tag', 'movement_id', $tag->id, $target->id);
            $this->movePivotRelations('planned_movement_tag', 'planned_movement_id', $tag->id, $target->id);
            $this->movePivotRelations('recurrence_template_tag', 'recurrence_template_id', $tag->id, $target->id);
            $tag->update([
                'archived_at' => now(),
                'merged_into_tag_id' => $target->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'tag', $tag->id, 'merged', $before, $tag->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Etiqueta fusionada con «'.$target->name.'» sin duplicar relaciones.');
    }

    private function validatedName(Request $request, Project $project, ?Tag $current = null): string
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $name = Str::of($validated['name'])->squish()->toString();
        $normalized = Str::of($name)->lower()->toString();
        if ($name === '' || $project->tags()->where('name_normalized', $normalized)->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una etiqueta con este nombre en el proyecto.']);
        }

        return $name;
    }

    private function movePivotRelations(string $table, string $relationColumn, int $sourceTagId, int $targetTagId): void
    {
        DB::table($table)->where('tag_id', $sourceTagId)->orderBy($relationColumn)
            ->chunkById(500, function ($relations) use ($table, $relationColumn, $targetTagId): void {
                $now = now();
                DB::table($table)->insertOrIgnore($relations->map(fn ($relation): array => [
                    $relationColumn => $relation->{$relationColumn},
                    'tag_id' => $targetTagId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }, $relationColumn);
        DB::table($table)->where('tag_id', $sourceTagId)->delete();
    }

    private function ensureTag(Project $project, Tag $tag): void
    {
        abort_unless($tag->project_id === $project->id, 404);
    }
}
