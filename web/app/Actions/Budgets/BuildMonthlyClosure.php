<?php

declare(strict_types=1);

namespace App\Actions\Budgets;

use App\Actions\Reports\BuildFinancialReport;
use App\Enums\FinancialAccountType;
use App\Models\MonthlyLeftoverAllocation;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BuildMonthlyClosure
{
    public function __construct(private readonly BuildFinancialReport $reports) {}

    /** @return array<string, mixed> */
    public function handle(Project $project, CarbonImmutable $month, ?int $excludeMovementId = null): array
    {
        $month = $month->startOfMonth();
        $report = $this->reports->month($project, $month);
        $allocations = MonthlyLeftoverAllocation::query()
            ->where('project_id', $project->id)
            ->whereDate('budget_month', $month->toDateString())
            ->when($excludeMovementId !== null, fn ($query) => $query->where('movement_id', '!=', $excludeMovementId))
            ->whereHas('movement', fn ($query) => $query->whereNull('trashed_at'))
            ->with(['movement.account', 'movement.destinationAccount', 'movement.goalAllocation.savingsGoal'])
            ->orderBy('id')
            ->get();
        $allocatedCents = (int) $allocations->sum(fn (MonthlyLeftoverAllocation $allocation): int => $allocation->movement->amount_cents);
        $positiveLeftover = max(0, (int) $report['remaining_cents']);

        return [
            ...$report,
            'allocations' => $allocations,
            'allocated_cents' => $allocatedCents,
            'allocated_savings_cents' => $this->sumForDestination($allocations, FinancialAccountType::Savings),
            'allocated_investment_cents' => $this->sumForDestination($allocations, FinancialAccountType::ExternalInvestment),
            'available_to_allocate_cents' => max(0, $positiveLeftover - $allocatedCents),
            'overallocated_cents' => max(0, $allocatedCents - $positiveLeftover),
        ];
    }

    /** @param Collection<int, MonthlyLeftoverAllocation> $allocations */
    private function sumForDestination(Collection $allocations, FinancialAccountType $type): int
    {
        return (int) $allocations->sum(fn (MonthlyLeftoverAllocation $allocation): int => $allocation->movement->destinationAccount?->type === $type
            ? $allocation->movement->amount_cents
            : 0);
    }
}
