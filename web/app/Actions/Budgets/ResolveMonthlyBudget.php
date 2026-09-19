<?php

declare(strict_types=1);

namespace App\Actions\Budgets;

use App\Models\MonthlyBudget;
use App\Models\MonthlyBudgetLimit;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ResolveMonthlyBudget
{
    public function preview(Project $project, CarbonInterface $month): MonthlyBudget
    {
        $monthDate = $month->copy()->startOfMonth()->toDateString();
        $existing = $project->monthlyBudgets()
            ->with('limits')
            ->whereDate('month', $monthDate)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $template = $project->budgetTemplates()
            ->with('limits')
            ->whereDate('effective_from_month', '<=', $monthDate)
            ->latest('effective_from_month')
            ->first();

        $previous = $template === null
            ? $project->monthlyBudgets()->with('limits')->whereDate('month', '<', $monthDate)->latest('month')->first()
            : null;

        $source = $template ?? $previous;
        $budget = new MonthlyBudget([
            'project_id' => $project->id,
            'month' => $monthDate,
            'total_limit_cents' => $source?->total_limit_cents ?? 0,
            'source_template_id' => $template?->id,
        ]);
        $budget->setRelation('limits', ($source?->limits ?? collect())->map(
            fn ($limit): MonthlyBudgetLimit => new MonthlyBudgetLimit([
                'category_id' => $limit->category_id,
                'limit_cents' => $limit->limit_cents,
            ]),
        ));

        return $budget;
    }

    public function handle(Project $project, CarbonInterface $month): MonthlyBudget
    {
        $monthDate = $month->copy()->startOfMonth()->toDateString();

        return DB::transaction(function () use ($project, $monthDate): MonthlyBudget {
            $existing = $project->monthlyBudgets()
                ->whereDate('month', $monthDate)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->load('limits');
            }

            $template = $project->budgetTemplates()
                ->with('limits')
                ->whereDate('effective_from_month', '<=', $monthDate)
                ->latest('effective_from_month')
                ->first();

            $previous = $template === null
                ? $project->monthlyBudgets()->with('limits')->whereDate('month', '<', $monthDate)->latest('month')->first()
                : null;

            $budget = $project->monthlyBudgets()->create([
                'month' => $monthDate,
                'total_limit_cents' => $template?->total_limit_cents ?? $previous?->total_limit_cents ?? 0,
                'source_template_id' => $template?->id,
            ]);

            foreach (($template?->limits ?? $previous?->limits ?? collect()) as $limit) {
                $budget->limits()->create([
                    'category_id' => $limit->category_id,
                    'limit_cents' => $limit->limit_cents,
                ]);
            }

            return $budget->load('limits');
        });
    }
}
