<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Enums\MovementType;
use App\Models\BudgetTemplate;
use App\Models\MonthlyBudget;
use App\Models\Movement;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

final class BuildProjectCardBudgetSummaries
{
    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{
     *     has_budget: bool,
     *     budget_cents: int,
     *     expense_cents: int,
     *     remaining_cents: int,
     *     percentage: int,
     *     progress_percentage: int,
     *     state: 'normal'|'warning'|'danger'
     * }>
     */
    public function handle(Collection $projects, CarbonInterface $month): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        $monthStart = $month->copy()->startOfMonth();
        $monthDate = $monthStart->toDateString();
        $projectIds = $projects->modelKeys();
        $activeProjectIds = $projects
            ->reject(fn (Project $project): bool => $project->isArchived())
            ->modelKeys();

        $exactBudgets = MonthlyBudget::query()
            ->whereIn('project_id', $projectIds)
            ->where('month', $monthDate)
            ->get()
            ->keyBy('project_id');

        $templates = $this->latestTemplates($activeProjectIds, $monthDate);
        $previousBudgets = $this->latestPreviousBudgets($activeProjectIds, $monthDate);
        $expenses = $this->monthlyExpenses(
            $projectIds,
            $monthDate,
            $monthStart->copy()->endOfMonth()->toDateString(),
        );

        $summaries = [];

        foreach ($projects as $project) {
            $budget = $exactBudgets->get($project->id);

            if ($budget === null && ! $project->isArchived()) {
                $budget = $templates->get($project->id) ?? $previousBudgets->get($project->id);
            }

            $expense = $expenses->get($project->id);
            $expenseCents = (int) ($expense?->expense_gross_cents ?? 0)
                - (int) ($expense?->refund_cents ?? 0);
            $budgetCents = (int) ($budget?->total_limit_cents ?? 0);
            $hasBudget = $budget !== null;
            $percentage = $this->percentage($budgetCents, $expenseCents);

            $summaries[$project->id] = [
                'has_budget' => $hasBudget,
                'budget_cents' => $budgetCents,
                'expense_cents' => $expenseCents,
                'remaining_cents' => $budgetCents - $expenseCents,
                'percentage' => $percentage,
                'progress_percentage' => max(0, min(100, $percentage)),
                'state' => $this->state($hasBudget, $percentage),
            ];
        }

        return $summaries;
    }

    /** @param list<int|string> $projectIds */
    private function latestTemplates(array $projectIds, string $monthDate): Collection
    {
        if ($projectIds === []) {
            return new Collection;
        }

        $latestDates = BudgetTemplate::query()
            ->select('project_id')
            ->selectRaw('MAX(effective_from_month) as latest_month')
            ->whereIn('project_id', $projectIds)
            ->where('effective_from_month', '<=', $monthDate)
            ->groupBy('project_id');

        return BudgetTemplate::query()
            ->joinSub($latestDates, 'latest_budget_templates', function (JoinClause $join): void {
                $join->on('budget_templates.project_id', '=', 'latest_budget_templates.project_id')
                    ->on('budget_templates.effective_from_month', '=', 'latest_budget_templates.latest_month');
            })
            ->get(['budget_templates.*'])
            ->keyBy('project_id');
    }

    /** @param list<int|string> $projectIds */
    private function latestPreviousBudgets(array $projectIds, string $monthDate): Collection
    {
        if ($projectIds === []) {
            return new Collection;
        }

        $latestDates = MonthlyBudget::query()
            ->select('project_id')
            ->selectRaw('MAX(month) as latest_month')
            ->whereIn('project_id', $projectIds)
            ->where('month', '<', $monthDate)
            ->groupBy('project_id');

        return MonthlyBudget::query()
            ->joinSub($latestDates, 'latest_monthly_budgets', function (JoinClause $join): void {
                $join->on('monthly_budgets.project_id', '=', 'latest_monthly_budgets.project_id')
                    ->on('monthly_budgets.month', '=', 'latest_monthly_budgets.latest_month');
            })
            ->get(['monthly_budgets.*'])
            ->keyBy('project_id');
    }

    /** @param list<int|string> $projectIds */
    private function monthlyExpenses(array $projectIds, string $monthStart, string $monthEnd): Collection
    {
        return Movement::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('trashed_at')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->select('project_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END), 0) as expense_gross_cents,
                COALESCE(SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END), 0) as refund_cents',
                [MovementType::Expense->value, MovementType::Refund->value],
            )
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');
    }

    private function percentage(int $budgetCents, int $expenseCents): int
    {
        if ($expenseCents <= 0) {
            return 0;
        }

        if ($budgetCents <= 0) {
            return 100;
        }

        return max(0, (int) round(($expenseCents / $budgetCents) * 100));
    }

    /** @return 'normal'|'warning'|'danger' */
    private function state(bool $hasBudget, int $percentage): string
    {
        if (! $hasBudget || $percentage < 80) {
            return 'normal';
        }

        return $percentage >= 100 ? 'danger' : 'warning';
    }
}
