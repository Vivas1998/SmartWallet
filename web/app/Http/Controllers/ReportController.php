<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reports\BuildFinancialReport;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function monthly(Request $request, Project $project, BuildFinancialReport $reports): View
    {
        $this->authorize('view', $project);
        $validated = $this->validatedFilters($request, ['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = CarbonImmutable::createFromFormat('Y-m-d', ($validated['month'] ?? now('Europe/Madrid')->format('Y-m')).'-01', 'Europe/Madrid');
        $filters = $this->filters($validated);

        return view('reports.monthly', [
            ...$this->viewData($project, $filters),
            'month' => $month,
            'report' => $reports->month($project, $month, $filters),
            'previous' => $reports->month($project, $month->subMonth(), $filters, false),
            'lastYear' => $reports->month($project, $month->subYear(), $filters, false),
        ]);
    }

    public function annual(Request $request, Project $project, BuildFinancialReport $reports): View
    {
        $this->authorize('view', $project);
        $validated = $this->validatedFilters($request, ['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($validated['year'] ?? now('Europe/Madrid')->year);
        $filters = $this->filters($validated);

        return view('reports.annual', [
            ...$this->viewData($project, $filters),
            'year' => $year,
            'report' => $reports->year($project, $year, $filters),
        ]);
    }

    public function compare(Request $request, Project $project, BuildFinancialReport $reports): View
    {
        $this->authorize('view', $project);
        $validated = $this->validatedFilters($request, [
            'mode' => ['nullable', Rule::in(['months', 'years'])],
            'periods' => ['nullable', 'array', 'max:5'],
            'periods.*' => ['nullable', 'string', 'max:7'],
        ]);
        $mode = $validated['mode'] ?? 'months';
        $periods = collect($validated['periods'] ?? [])->filter()->unique()->values();
        if ($periods->isEmpty()) {
            $now = CarbonImmutable::now('Europe/Madrid');
            $periods = $mode === 'months'
                ? collect([$now->format('Y-m'), $now->subMonth()->format('Y-m'), $now->subYear()->format('Y-m')])
                : collect([(string) $now->year, (string) ($now->year - 1), (string) ($now->year - 2)]);
        }
        $pattern = $mode === 'months' ? '/^\d{4}-(0[1-9]|1[0-2])$/' : '/^(19|20|21)\d{2}$/';
        if ($periods->contains(fn (string $period): bool => preg_match($pattern, $period) !== 1)) {
            throw ValidationException::withMessages(['periods' => $mode === 'months' ? 'Usa meses válidos con formato AAAA-MM.' : 'Usa años válidos entre 1900 y 2199.']);
        }
        $filters = $this->filters($validated);
        $comparisons = $periods->map(fn (string $period): array => $mode === 'months'
            ? $reports->month($project, CarbonImmutable::createFromFormat('Y-m-d', $period.'-01', 'Europe/Madrid'), $filters)
            : $reports->year($project, (int) $period, $filters, true, false));

        return view('reports.compare', [
            ...$this->viewData($project, $filters),
            'mode' => $mode,
            'periods' => $periods,
            'comparisons' => $comparisons,
        ]);
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    private function validatedFilters(Request $request, array $extra): array
    {
        return $request->validate([
            'account' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'member' => ['nullable', 'integer'],
            'tag' => ['nullable', 'integer'],
            ...$extra,
        ]);
    }

    /** @param array<string, mixed> $validated @return array<string, int|null> */
    private function filters(array $validated): array
    {
        return collect(['account', 'category', 'member', 'tag'])->mapWithKeys(fn (string $key): array => [$key => isset($validated[$key]) ? (int) $validated[$key] : null])->all();
    }

    /** @param array<string, int|null> $filters @return array<string, mixed> */
    private function viewData(Project $project, array $filters): array
    {
        return [
            'project' => $project,
            'filters' => $filters,
            'accounts' => $project->financialAccounts()->orderByRaw('archived_at is not null')->orderBy('position')->get(),
            'categories' => $project->categories()->whereNull('parent_id')->orderByRaw('archived_at is not null')->orderBy('position')->get(),
            'members' => $project->activeMembers()->orderBy('name')->get(),
            'tags' => $project->tags()->orderByRaw('archived_at is not null')->orderBy('name')->get(),
        ];
    }
}
