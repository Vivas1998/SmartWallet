<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\MovementType;
use App\Models\Project;
use Carbon\CarbonInterface;

final class BuildProjectMonthSummary
{
    /** @return array{expense_gross_cents: int, refund_cents: int, expense_cents: int, income_cents: int} */
    public function handle(Project $project, CarbonInterface $month): array
    {
        $expense = MovementType::Expense->value;
        $refund = MovementType::Refund->value;
        $income = MovementType::Income->value;
        $month = $month->copy()->startOfMonth();
        $row = $project->movements()
            ->whereNull('trashed_at')
            ->whereBetween('occurred_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$expense}' THEN amount_cents ELSE 0 END), 0) as expense_gross_cents,
                COALESCE(SUM(CASE WHEN type = '{$refund}' THEN amount_cents ELSE 0 END), 0) as refund_cents,
                COALESCE(SUM(CASE WHEN type = '{$income}' THEN amount_cents ELSE 0 END), 0) as income_cents")
            ->first();
        $expenseGrossCents = (int) ($row?->expense_gross_cents ?? 0);
        $refundCents = (int) ($row?->refund_cents ?? 0);

        return [
            'expense_gross_cents' => $expenseGrossCents,
            'refund_cents' => $refundCents,
            'expense_cents' => $expenseGrossCents - $refundCents,
            'income_cents' => (int) ($row?->income_cents ?? 0),
        ];
    }
}
