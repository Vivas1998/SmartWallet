<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Budgets\ResolveMonthlyBudget;
use App\Enums\CategoryType;
use App\Enums\MovementType;
use App\Models\BudgetTemplate;
use App\Models\Category;
use App\Models\MonthlyBudget;
use App\Models\Project;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request, Project $project, ResolveMonthlyBudget $resolveBudget): View
    {
        $this->authorize('view', $project);
        $month = $this->monthFrom($request->query('month'));
        $budget = $project->isArchived()
            ? $resolveBudget->preview($project, $month)
            : $resolveBudget->handle($project, $month);

        $categories = $project->categories()
            ->whereNull('parent_id')
            ->where('type', CategoryType::Expense->value)
            ->with(['children' => fn ($query) => $query
                ->where('type', CategoryType::Expense->value)
                ->orderByRaw('archived_at is not null')
                ->orderBy('position')
                ->orderBy('name')])
            ->orderByRaw('archived_at is not null')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $expense = MovementType::Expense->value;
        $refund = MovementType::Refund->value;
        $spentRows = $project->movements()
            ->whereNull('trashed_at')
            ->whereIn('type', [$expense, $refund])
            ->whereBetween('occurred_on', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->selectRaw("category_id, subcategory_id, SUM(CASE WHEN type = '{$expense}' THEN amount_cents ELSE -amount_cents END) as total_cents")
            ->groupBy('category_id', 'subcategory_id')
            ->get();

        $spentByCategory = collect();
        $spentBySubcategory = collect();
        $spentWithoutSubcategory = collect();
        $spentCents = 0;

        foreach ($spentRows as $row) {
            $value = (int) $row->total_cents;
            $spentCents += $value;

            if ($row->category_id === null) {
                continue;
            }

            $categoryId = (int) $row->category_id;
            $spentByCategory[$categoryId] = (int) ($spentByCategory[$categoryId] ?? 0) + $value;

            if ($row->subcategory_id === null) {
                $spentWithoutSubcategory[$categoryId] = (int) ($spentWithoutSubcategory[$categoryId] ?? 0) + $value;
            } else {
                $subcategoryId = (int) $row->subcategory_id;
                $spentBySubcategory[$subcategoryId] = (int) ($spentBySubcategory[$subcategoryId] ?? 0) + $value;
            }
        }

        return view('budgets.index', [
            'project' => $project,
            'month' => $month,
            'budget' => $budget,
            'categories' => $categories,
            'limits' => $budget->limits->keyBy('category_id'),
            'spentByCategory' => $spentByCategory,
            'spentBySubcategory' => $spentBySubcategory,
            'spentWithoutSubcategory' => $spentWithoutSubcategory,
            'spentCents' => $spentCents,
            'remainingCents' => $budget->total_limit_cents - $spentCents,
            'canManage' => $request->user()->can('manageBudgets', $project),
        ]);
    }

    public function update(Request $request, Project $project, ResolveMonthlyBudget $resolveBudget, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageBudgets', $project);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'total_limit' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'scope' => ['required', Rule::in(['month', 'future'])],
        ], [
            'total_limit.regex' => 'Introduce un presupuesto total válido y sin signo negativo.',
            'limits.*.regex' => 'Los límites por categoría deben ser importes válidos y sin signo negativo.',
        ]);

        $month = $this->monthFrom($validated['month']);
        $totalCents = Money::toCents($validated['total_limit']);
        $expenseCategories = $project->categories()
            ->where('type', CategoryType::Expense->value)
            ->with('parent:id,archived_at')
            ->get();
        $activeMainIds = $expenseCategories
            ->filter(fn (Category $category): bool => $category->isMain() && ! $category->isArchived())
            ->modelKeys();
        $editableCategories = $expenseCategories
            ->filter(fn (Category $category): bool => ! $category->isArchived()
                && ($category->isMain() || in_array($category->parent_id, $activeMainIds, true)));
        $editableCategoryIds = $editableCategories->modelKeys();
        $allExpenseCategoryIds = $expenseCategories->modelKeys();
        $submittedLimits = $validated['limits'] ?? [];
        $submittedCategoryIds = array_map('intval', array_keys($submittedLimits));

        if (array_diff($submittedCategoryIds, $editableCategoryIds) !== []) {
            throw ValidationException::withMessages([
                'limits' => 'Solo puedes asignar límites a categorías y subcategorías de gasto activas de este proyecto.',
            ]);
        }

        $limits = [];
        foreach ($editableCategoryIds as $categoryId) {
            $raw = (string) ($submittedLimits[$categoryId] ?? '0');
            $limits[(int) $categoryId] = $raw === '' ? 0 : Money::toCents($raw);
        }

        if ($totalCents < 0 || collect($limits)->contains(fn (int $value): bool => $value < 0)) {
            throw ValidationException::withMessages(['total_limit' => 'El presupuesto no puede ser negativo.']);
        }

        $preview = $resolveBudget->preview($project, $month);
        $effectiveLimits = $preview->limits
            ->mapWithKeys(fn ($limit): array => [(int) $limit->category_id => (int) $limit->limit_cents])
            ->all();

        foreach ($limits as $categoryId => $limitCents) {
            $effectiveLimits[$categoryId] = $limitCents;
        }

        foreach ($expenseCategories->whereNull('parent_id')->whereNull('archived_at') as $category) {
            $childLimitCents = $expenseCategories
                ->where('parent_id', $category->id)
                ->sum(fn (Category $child): int => (int) ($effectiveLimits[$child->id] ?? 0));
            $parentLimitCents = (int) ($effectiveLimits[$category->id] ?? 0);

            if ($childLimitCents > $parentLimitCents) {
                throw ValidationException::withMessages([
                    'limits.'.$category->id => 'La suma de límites de las subcategorías de '.$category->name.' no puede superar su límite principal.',
                ]);
            }
        }

        $budget = $resolveBudget->handle($project, $month);
        $before = $this->budgetSnapshot($budget->load('limits'));

        DB::transaction(function () use ($request, $project, $budget, $month, $totalCents, $limits, $editableCategoryIds, $allExpenseCategoryIds, $validated, $before, $audit): void {
            $budget->update([
                'total_limit_cents' => $totalCents,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $this->replaceEditableLimits($budget, $limits, $editableCategoryIds);

            if ($validated['scope'] === 'future') {
                $template = BudgetTemplate::query()->updateOrCreate(
                    ['project_id' => $project->id, 'effective_from_month' => $month->toDateString()],
                    [
                        'total_limit_cents' => $totalCents,
                        'created_by_user_id' => $request->user()->id,
                        'updated_by_user_id' => $request->user()->id,
                    ],
                );
                $template->limits()->whereIn('category_id', $allExpenseCategoryIds)->delete();
                foreach ($limits as $categoryId => $limitCents) {
                    $template->limits()->create(['category_id' => $categoryId, 'limit_cents' => $limitCents]);
                }
                $budget->update(['source_template_id' => $template->id]);
            }

            $audit->handle($project, $request->user(), 'budget', $budget->id, 'updated', $before, $this->budgetSnapshot($budget->fresh()->load('limits')));
        });

        return redirect()
            ->route('budgets.index', ['project' => $project, 'month' => $month->format('Y-m')])
            ->with('status', $validated['scope'] === 'future'
                ? 'Presupuesto guardado para este mes y usado como base de los próximos.'
                : 'Presupuesto actualizado únicamente para este mes.');
    }

    /** @param array<int, int> $limits @param list<int> $editableCategoryIds */
    private function replaceEditableLimits(MonthlyBudget $budget, array $limits, array $editableCategoryIds): void
    {
        $budget->limits()->whereIn('category_id', $editableCategoryIds)->delete();
        foreach ($limits as $categoryId => $limitCents) {
            $budget->limits()->create(['category_id' => $categoryId, 'limit_cents' => $limitCents]);
        }
    }

    private function monthFrom(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return CarbonImmutable::now('Europe/Madrid')->startOfMonth();
        }

        return CarbonImmutable::parse($value.'-01', 'Europe/Madrid')->startOfMonth();
    }

    /** @return array<string, mixed> */
    private function budgetSnapshot(MonthlyBudget $budget): array
    {
        return [
            'month' => $budget->month->toDateString(),
            'total_limit_cents' => $budget->total_limit_cents,
            'limits' => $budget->limits->mapWithKeys(fn ($limit): array => [(string) $limit->category_id => $limit->limit_cents])->all(),
        ];
    }
}
