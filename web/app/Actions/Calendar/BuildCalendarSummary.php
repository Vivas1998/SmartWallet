<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Budgets\ResolveMonthlyBudget;
use App\Actions\Reports\BuildProjectMonthSummary;
use App\Enums\CalendarEventStatus;
use App\Enums\MovementType;
use App\Models\Project;
use Carbon\CarbonInterface;

final class BuildCalendarSummary
{
    public function __construct(
        private readonly ResolveMonthlyBudget $resolveBudget,
        private readonly BuildProjectMonthSummary $monthSummary,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, int>
     */
    public function handle(Project $project, CarbonInterface $month, array $events): array
    {
        $budgetCents = (int) $this->resolveBudget->preview($project, $month)->total_limit_cents;
        $actual = $this->monthSummary->handle($project, $month);
        $completed = collect($events)->where('status', CalendarEventStatus::Completed->value);
        $pending = collect($events)->whereIn('status', [
            CalendarEventStatus::Planned->value,
            CalendarEventStatus::Today->value,
            CalendarEventStatus::Overdue->value,
        ]);

        $calendarExpenseCents = (int) $completed
            ->where('type', MovementType::Expense->value)
            ->sum('amount_cents')
            - (int) $completed
                ->where('type', MovementType::Refund->value)
                ->sum('amount_cents');
        $pendingExpenseCents = (int) $pending
            ->where('type', MovementType::Expense->value)
            ->sum('amount_cents');
        $pendingIncomeCents = (int) $pending
            ->where('type', MovementType::Income->value)
            ->sum('amount_cents');
        $actualExpenseCents = (int) $actual['expense_cents'];

        return [
            'budget_cents' => $budgetCents,
            'actual_expense_cents' => $actualExpenseCents,
            'calendar_expense_cents' => $calendarExpenseCents,
            'pending_expense_cents' => $pendingExpenseCents,
            'forecast_expense_cents' => $actualExpenseCents + $pendingExpenseCents,
            'pending_income_cents' => $pendingIncomeCents,
            'actual_remaining_cents' => $budgetCents - $actualExpenseCents,
            'forecast_remaining_cents' => $budgetCents - $actualExpenseCents - $pendingExpenseCents,
            'calendar_expense_count' => $completed->whereIn('type', [
                MovementType::Expense->value,
                MovementType::Refund->value,
            ])->count(),
            'pending_expense_count' => $pending->where('type', MovementType::Expense->value)->count(),
            'pending_income_count' => $pending->where('type', MovementType::Income->value)->count(),
        ];
    }
}
