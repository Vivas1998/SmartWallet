<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Actions\Audit\RecordProjectAudit;
use App\Enums\ProjectRole;
use App\Models\BudgetTemplate;
use App\Models\FinancialAccount;
use App\Models\MonthlyBudget;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\InitialCategoryCatalog;
use App\Support\InitialCustomFieldCatalog;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateProject
{
    public function __construct(
        private readonly InitialCategoryCatalog $initialCategoryCatalog,
        private readonly InitialCustomFieldCatalog $initialCustomFieldCatalog,
        private readonly RecordProjectAudit $audit,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     color: string,
     *     icon: string,
     *     account_name: string,
     *     account_type: string,
     *     initial_balance: string,
     *     initial_balance_date: string,
     *     monthly_budget: string
     * } $data
     */
    public function handle(User $creator, array $data): Project
    {
        return DB::transaction(function () use ($creator, $data): Project {
            $project = Project::create([
                'creator_user_id' => $creator->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'],
                'icon' => $data['icon'],
                'currency' => 'EUR',
                'locale' => 'es',
                'timezone' => 'Europe/Madrid',
            ]);

            $membership = ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $creator->id,
                'role' => ProjectRole::Owner,
                'added_by_user_id' => $creator->id,
                'joined_at' => now(),
            ]);

            $account = FinancialAccount::create([
                'project_id' => $project->id,
                'name' => $data['account_name'],
                'type' => $data['account_type'],
                'initial_balance_cents' => Money::toCents($data['initial_balance']),
                'initial_balance_date' => $data['initial_balance_date'],
                'color' => $data['color'],
                'icon' => 'wallet',
                'position' => 0,
                'created_by_user_id' => $creator->id,
                'updated_by_user_id' => $creator->id,
            ]);

            $this->initialCategoryCatalog->createFor($project, $creator);
            $this->initialCustomFieldCatalog->createFor($project, $creator);

            $budgetMonth = CarbonImmutable::now('Europe/Madrid')->startOfMonth()->toDateString();
            $budgetCents = Money::toCents($data['monthly_budget']);
            $budgetTemplate = BudgetTemplate::create([
                'project_id' => $project->id,
                'effective_from_month' => $budgetMonth,
                'total_limit_cents' => $budgetCents,
                'created_by_user_id' => $creator->id,
                'updated_by_user_id' => $creator->id,
            ]);
            $monthlyBudget = MonthlyBudget::create([
                'project_id' => $project->id,
                'month' => $budgetMonth,
                'total_limit_cents' => $budgetCents,
                'source_template_id' => $budgetTemplate->id,
                'created_by_user_id' => $creator->id,
                'updated_by_user_id' => $creator->id,
            ]);

            $this->audit->handle($project, $creator, 'project', $project->id, 'created', null, $project->auditSnapshot());
            $this->audit->handle($project, $creator, 'member', $membership->id, 'created', null, [
                'user_id' => $membership->user_id,
                'role' => $membership->role->value,
                'joined_at' => $membership->joined_at?->toIso8601String(),
                'removed_at' => null,
            ]);
            $this->audit->handle($project, $creator, 'account', $account->id, 'created', null, $account->auditSnapshot());
            $this->audit->handle($project, $creator, 'budget', $monthlyBudget->id, 'created', null, [
                'month' => $budgetMonth,
                'total_limit_cents' => $budgetCents,
                'limits' => [],
            ]);

            return $project;
        });
    }
}
