<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\FinancialAccountType;
use App\Enums\GoalAllocationDirection;
use App\Enums\MovementType;
use App\Models\Movement;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BuildFinancialReport
{
    /** @param array<string, int|null> $filters @return array<string, mixed> */
    public function month(Project $project, CarbonImmutable $month, array $filters = [], bool $withCategories = true): array
    {
        $month = $month->startOfMonth();
        $stats = $this->aggregate($this->movements($project, $month, $month->endOfMonth(), $filters));
        $budget = $this->budgetSeries($project, [$month])[$month->format('Y-m')];

        return [
            'key' => $month->format('Y-m'),
            'label' => ucfirst($month->locale('es')->translatedFormat('F Y')),
            'start' => $month,
            'end' => $month->endOfMonth(),
            ...$stats,
            'budget_cents' => $budget,
            'actual_budget_cents' => $budget,
            'remaining_cents' => $budget - $stats['expense_cents'],
            'categories' => $withCategories ? $this->categories($this->movements($project, $month, $month->endOfMonth(), $filters)) : collect(),
            'planned' => $month->isAfter(CarbonImmutable::now('Europe/Madrid')->startOfMonth()),
        ];
    }

    /** @param array<string, int|null> $filters @return array<string, mixed> */
    public function year(Project $project, int $year, array $filters = [], bool $withCategories = true, bool $withMonths = true): array
    {
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Europe/Madrid');
        $end = $start->endOfYear();
        $base = $this->movements($project, $start, $end, $filters);
        $stats = $this->aggregate($base);
        $monthDates = collect(range(1, 12))->map(fn (int $number): CarbonImmutable => $start->month($number));
        $budgets = $this->budgetSeries($project, $monthDates->all());
        $now = CarbonImmutable::now('Europe/Madrid')->startOfMonth();
        $monthStats = $withMonths ? $this->aggregateByMonth($base) : collect();
        $months = $monthDates->map(function (CarbonImmutable $month) use ($budgets, $monthStats, $now): array {
            $key = $month->format('Y-m');
            $values = $monthStats->get($key, $this->emptyStats());
            $budget = $budgets[$key];

            return [
                'key' => $key,
                'label' => ucfirst($month->locale('es')->translatedFormat('M')),
                ...$values,
                'budget_cents' => $budget,
                'remaining_cents' => $budget - $values['expense_cents'],
                'planned' => $month->isAfter($now),
            ];
        });
        $actualBudget = (int) $months->where('planned', false)->sum('budget_cents');
        $plannedBudget = (int) $months->sum('budget_cents');

        return [
            'key' => (string) $year,
            'label' => (string) $year,
            'start' => $start,
            'end' => $end,
            ...$stats,
            'budget_cents' => $plannedBudget,
            'actual_budget_cents' => $actualBudget,
            'remaining_cents' => $actualBudget - $stats['expense_cents'],
            'months' => $months,
            'categories' => $withCategories ? $this->categories($base) : collect(),
            'planned' => $start->isAfter($now->endOfYear()),
        ];
    }

    /** @param array<string, int|null> $filters */
    private function movements(Project $project, CarbonImmutable $start, CarbonImmutable $end, array $filters): Builder
    {
        return Movement::query()
            ->where('movements.project_id', $project->id)
            ->whereNull('movements.trashed_at')
            ->whereBetween('movements.occurred_on', [$start->toDateString(), $end->toDateString()])
            ->when($filters['account'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $accounts) => $accounts->where('movements.financial_account_id', $id)->orWhere('movements.destination_account_id', $id)))
            ->when($filters['category'] ?? null, fn (Builder $query, int $id) => $query->where('movements.category_id', $id))
            ->when($filters['member'] ?? null, fn (Builder $query, int $id) => $query->where('movements.paid_by_user_id', $id))
            ->when($filters['tag'] ?? null, fn (Builder $query, int $id) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($id)));
    }

    /** @return array<string, int> */
    private function aggregate(Builder $query): array
    {
        $row = (clone $query)
            ->leftJoin('financial_accounts as report_source', 'movements.financial_account_id', '=', 'report_source.id')
            ->leftJoin('financial_accounts as report_destination', 'movements.destination_account_id', '=', 'report_destination.id')
            ->leftJoin('goal_allocations as report_goal', 'movements.id', '=', 'report_goal.movement_id')
            ->toBase()->selectRaw($this->aggregateSql())->first();

        return $this->statsFrom($row);
    }

    /** @return Collection<string, array<string, int>> */
    private function aggregateByMonth(Builder $query)
    {
        return (clone $query)
            ->leftJoin('financial_accounts as report_source', 'movements.financial_account_id', '=', 'report_source.id')
            ->leftJoin('financial_accounts as report_destination', 'movements.destination_account_id', '=', 'report_destination.id')
            ->leftJoin('goal_allocations as report_goal', 'movements.id', '=', 'report_goal.movement_id')
            ->toBase()
            ->selectRaw("DATE_FORMAT(movements.occurred_on, '%Y-%m') as period, ".$this->aggregateSql())
            ->groupBy('period')->get()->mapWithKeys(fn (object $row): array => [$row->period => $this->statsFrom($row)]);
    }

    private function aggregateSql(): string
    {
        $expense = MovementType::Expense->value;
        $refund = MovementType::Refund->value;
        $income = MovementType::Income->value;
        $transfer = MovementType::Transfer->value;
        $investment = MovementType::InvestmentContribution->value;
        $external = FinancialAccountType::ExternalInvestment->value;
        $contribution = GoalAllocationDirection::Contribution->value;
        $withdrawal = GoalAllocationDirection::Withdrawal->value;

        return "COUNT(movements.id) as movement_count,
            COALESCE(SUM(CASE WHEN movements.type = '{$expense}' THEN movements.amount_cents ELSE 0 END), 0) as expense_gross_cents,
            COALESCE(SUM(CASE WHEN movements.type = '{$refund}' THEN movements.amount_cents ELSE 0 END), 0) as refund_cents,
            COALESCE(SUM(CASE WHEN movements.type = '{$income}' THEN movements.amount_cents ELSE 0 END), 0) as income_cents,
            COALESCE(SUM(CASE WHEN movements.type = '{$transfer}' THEN movements.amount_cents ELSE 0 END), 0) as transfer_cents,
            COALESCE(SUM(CASE WHEN movements.type = '{$investment}' AND report_destination.type = '{$external}' THEN movements.amount_cents ELSE 0 END), 0) as investment_contribution_cents,
            COALESCE(SUM(CASE WHEN movements.type = '{$investment}' AND report_source.type = '{$external}' THEN movements.amount_cents ELSE 0 END), 0) as investment_withdrawal_cents,
            COALESCE(SUM(CASE WHEN report_goal.direction = '{$contribution}' THEN movements.amount_cents ELSE 0 END), 0) as savings_contribution_cents,
            COALESCE(SUM(CASE WHEN report_goal.direction = '{$withdrawal}' THEN movements.amount_cents ELSE 0 END), 0) as savings_withdrawal_cents";
    }

    /** @return array<string, int> */
    private function statsFrom(?object $row): array
    {
        $gross = (int) ($row?->expense_gross_cents ?? 0);
        $refunds = (int) ($row?->refund_cents ?? 0);
        $expenses = $gross - $refunds;
        $income = (int) ($row?->income_cents ?? 0);

        return [
            'movement_count' => (int) ($row?->movement_count ?? 0),
            'expense_gross_cents' => $gross,
            'refund_cents' => $refunds,
            'expense_cents' => $expenses,
            'income_cents' => $income,
            'balance_cents' => $income - $expenses,
            'transfer_cents' => (int) ($row?->transfer_cents ?? 0),
            'investment_contribution_cents' => (int) ($row?->investment_contribution_cents ?? 0),
            'investment_withdrawal_cents' => (int) ($row?->investment_withdrawal_cents ?? 0),
            'savings_contribution_cents' => (int) ($row?->savings_contribution_cents ?? 0),
            'savings_withdrawal_cents' => (int) ($row?->savings_withdrawal_cents ?? 0),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function categories(Builder $query)
    {
        $expense = MovementType::Expense->value;
        $refund = MovementType::Refund->value;

        return (clone $query)->whereIn('movements.type', [$expense, $refund])
            ->leftJoin('categories as report_category', 'movements.category_id', '=', 'report_category.id')
            ->toBase()->selectRaw("movements.category_id, COALESCE(report_category.name, 'Sin categoría') as name, COALESCE(report_category.color, '#718096') as color,
                SUM(CASE WHEN movements.type = '{$expense}' THEN movements.amount_cents ELSE 0 END) as gross_cents,
                SUM(CASE WHEN movements.type = '{$refund}' THEN movements.amount_cents ELSE 0 END) as refund_cents,
                SUM(CASE WHEN movements.type = '{$expense}' THEN movements.amount_cents WHEN movements.type = '{$refund}' THEN -movements.amount_cents ELSE 0 END) as expense_cents")
            ->groupBy('movements.category_id', 'report_category.name', 'report_category.color')
            ->orderByDesc('expense_cents')->get()->map(fn (object $row): array => [
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'name' => $row->name,
                'color' => $row->color,
                'gross_cents' => (int) $row->gross_cents,
                'refund_cents' => (int) $row->refund_cents,
                'expense_cents' => (int) $row->expense_cents,
            ]);
    }

    /** @param list<CarbonImmutable> $months @return array<string, int> */
    private function budgetSeries(Project $project, array $months): array
    {
        $keys = collect($months)->map(fn (CarbonImmutable $month): string => $month->format('Y-m'))->unique()->sort()->values();
        $first = $keys->first().'-01';
        $last = $keys->last().'-01';
        $existing = $project->monthlyBudgets()->whereBetween('month', [$first, $last])->orderBy('month')->get();
        $templates = $project->budgetTemplates()->whereDate('effective_from_month', '<=', $last)->orderBy('effective_from_month')->get();
        $previous = $project->monthlyBudgets()->whereDate('month', '<=', $last)->orderBy('month')->get();

        return $keys->mapWithKeys(function (string $key) use ($existing, $templates, $previous): array {
            $exact = $existing->first(fn ($budget): bool => $budget->month->format('Y-m') === $key);
            $template = $templates->last(fn ($item): bool => $item->effective_from_month->format('Y-m') <= $key);
            $prior = $previous->last(fn ($budget): bool => $budget->month->format('Y-m') < $key);

            return [$key => (int) ($exact?->total_limit_cents ?? $template?->total_limit_cents ?? $prior?->total_limit_cents ?? 0)];
        })->all();
    }

    /** @return array<string, int> */
    private function emptyStats(): array
    {
        return $this->statsFrom(null);
    }
}
