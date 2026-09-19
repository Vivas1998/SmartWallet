<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Reports\BuildFinancialReport;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\GoalAllocationDirection;
use App\Enums\MovementType;
use App\Enums\ProjectRole;
use App\Models\BudgetTemplate;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\GoalAllocation;
use App\Models\MonthlyBudget;
use App\Models\Movement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\SavingsGoal;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        CarbonImmutable::setTestNow('2026-09-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_report_separates_financial_concepts_and_categories(): void
    {
        [$project, $owner, , $checking, $savings, $investment, $food, $leisure] = $this->financialProject();
        $this->budget($project, $owner, '2026-01-01', 100000);
        $this->movement($project, $owner, $checking, null, $food, MovementType::Expense, 40000, 'Compra', '2026-09-03');
        $this->movement($project, $owner, $checking, null, $leisure, MovementType::Expense, 10000, 'Cine', '2026-09-04');
        $this->movement($project, $owner, $checking, null, $food, MovementType::Refund, 5000, 'Devolución compra', '2026-09-05');
        $this->movement($project, $owner, $checking, null, null, MovementType::Income, 200000, 'Nómina', '2026-09-01');
        $contribution = $this->movement($project, $owner, $checking, $savings, null, MovementType::Transfer, 25000, 'Ahorro', '2026-09-06');
        $withdrawal = $this->movement($project, $owner, $savings, $checking, null, MovementType::Transfer, 3000, 'Retirada ahorro', '2026-09-07');
        $this->movement($project, $owner, $checking, $investment, null, MovementType::InvestmentContribution, 10000, 'Invertir', '2026-09-08');
        $this->movement($project, $owner, $investment, $checking, null, MovementType::InvestmentContribution, 2000, 'Retirar inversión', '2026-09-09');
        $goal = SavingsGoal::create(['project_id' => $project->id, 'financial_account_id' => $savings->id, 'name' => 'Fondo', 'target_amount_cents' => 500000, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        GoalAllocation::create(['savings_goal_id' => $goal->id, 'movement_id' => $contribution->id, 'direction' => GoalAllocationDirection::Contribution]);
        GoalAllocation::create(['savings_goal_id' => $goal->id, 'movement_id' => $withdrawal->id, 'direction' => GoalAllocationDirection::Withdrawal]);

        $report = app(BuildFinancialReport::class)->month($project, CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(50000, $report['expense_gross_cents']);
        $this->assertSame(5000, $report['refund_cents']);
        $this->assertSame(45000, $report['expense_cents']);
        $this->assertSame(200000, $report['income_cents']);
        $this->assertSame(155000, $report['balance_cents']);
        $this->assertSame(100000, $report['budget_cents']);
        $this->assertSame(55000, $report['remaining_cents']);
        $this->assertSame(28000, $report['transfer_cents']);
        $this->assertSame(10000, $report['investment_contribution_cents']);
        $this->assertSame(2000, $report['investment_withdrawal_cents']);
        $this->assertSame(25000, $report['savings_contribution_cents']);
        $this->assertSame(3000, $report['savings_withdrawal_cents']);
        $this->assertSame(35000, $report['categories']->firstWhere('category_id', $food->id)['expense_cents']);

        $this->actingAs($owner)->get(route('reports.monthly', ['project' => $project, 'month' => '2026-09']))
            ->assertOk()->assertSee('450,00 €')->assertSee('Operaciones separadas')->assertSee('Alimentación')
            ->assertSee('aria-current="page"', false);
    }

    public function test_annual_report_keeps_future_planning_separate_from_elapsed_budget(): void
    {
        [$project, $owner, , $checking, , $investment, $food] = $this->financialProject();
        $this->budget($project, $owner, '2026-01-01', 100000);
        MonthlyBudget::create(['project_id' => $project->id, 'month' => '2026-09-01', 'total_limit_cents' => 120000, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $this->movement($project, $owner, $checking, null, $food, MovementType::Expense, 40000, 'Enero', '2026-01-10');
        $this->movement($project, $owner, $checking, null, null, MovementType::Income, 200000, 'Septiembre', '2026-09-01');
        $this->movement($project, $owner, $checking, $investment, null, MovementType::InvestmentContribution, 10000, 'Invertir', '2026-02-01');

        $report = app(BuildFinancialReport::class)->year($project, 2026);

        $this->assertSame(1220000, $report['budget_cents']);
        $this->assertSame(920000, $report['actual_budget_cents']);
        $this->assertSame(880000, $report['remaining_cents']);
        $this->assertSame(10000, $report['investment_contribution_cents']);
        $this->assertFalse($report['months']->firstWhere('key', '2026-09')['planned']);
        $this->assertTrue($report['months']->firstWhere('key', '2026-10')['planned']);

        $this->actingAs($owner)->get(route('reports.annual', ['project' => $project, 'year' => 2026]))
            ->assertOk()->assertSee('12.200,00 €')->assertSee('Planificación')->assertSee('Evolución de ahorro e inversión');
    }

    public function test_comparison_applies_tags_and_is_restricted_to_project_members(): void
    {
        [$project, $owner, $member, $checking, , , $food] = $this->financialProject();
        $selected = $this->tag($project, $owner, 'Navidad');
        $other = $this->tag($project, $owner, 'Trabajo');
        $this->movement($project, $owner, $checking, null, $food, MovementType::Expense, 8000, 'Regalo actual', '2026-01-05')->tags()->attach($selected);
        $this->movement($project, $owner, $checking, null, $food, MovementType::Expense, 5000, 'Regalo anterior', '2025-01-05')->tags()->attach($selected);
        $this->movement($project, $owner, $checking, null, $food, MovementType::Expense, 3000, 'No incluido', '2026-01-07')->tags()->attach($other);

        $this->actingAs($member)->get(route('reports.compare', ['project' => $project, 'mode' => 'months', 'periods' => ['2026-01', '2025-01'], 'tag' => $selected->id]))
            ->assertOk()->assertSee('80,00 €')->assertSee('50,00 €')->assertSee('-30,00 € · -37,5 %')->assertSee('Categorías por periodo')->assertSee('Alimentación');
        $this->actingAs($owner)->get(route('reports.compare', ['project' => $project, 'mode' => 'months', 'periods' => ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06']]))
            ->assertSessionHasErrors('periods');
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('reports.annual', ['project' => $project, 'year' => 2026]))->assertForbidden();
    }

    /** @return array{Project, User, User, FinancialAccount, FinancialAccount, FinancialAccount, Category, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => ProjectRole::Owner, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::Member, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        $checking = $this->account($project, $owner, 'Principal', FinancialAccountType::Checking);
        $savings = $this->account($project, $owner, 'Ahorro', FinancialAccountType::Savings);
        $investment = $this->account($project, $owner, 'Cartera externa', FinancialAccountType::ExternalInvestment);
        $food = $this->category($project, $owner, 'Alimentación');
        $leisure = $this->category($project, $owner, 'Ocio');

        return [$project, $owner, $member, $checking, $savings, $investment, $food, $leisure];
    }

    private function account(Project $project, User $owner, string $name, FinancialAccountType $type): FinancialAccount
    {
        return FinancialAccount::create(['project_id' => $project->id, 'name' => $name, 'type' => $type, 'initial_balance_cents' => 0, 'initial_balance_date' => '2025-01-01', 'position' => 10, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    private function category(Project $project, User $owner, string $name): Category
    {
        return Category::create(['project_id' => $project->id, 'type' => CategoryType::Expense, 'name' => $name, 'color' => '#147d68', 'icon' => 'cart', 'position' => 10, 'is_initial' => false, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    private function budget(Project $project, User $owner, string $month, int $cents): BudgetTemplate
    {
        return BudgetTemplate::create(['project_id' => $project->id, 'effective_from_month' => $month, 'total_limit_cents' => $cents, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    private function movement(Project $project, User $owner, FinancialAccount $source, ?FinancialAccount $destination, ?Category $category, MovementType $type, int $amount, string $concept, string $date): Movement
    {
        return Movement::create(['project_id' => $project->id, 'type' => $type, 'amount_cents' => $amount, 'occurred_on' => $date, 'concept' => $concept, 'category_id' => $category?->id, 'financial_account_id' => $source->id, 'destination_account_id' => $destination?->id, 'paid_by_user_id' => in_array($type, [MovementType::Expense, MovementType::Income, MovementType::Refund], true) ? $owner->id : null, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    private function tag(Project $project, User $owner, string $name): Tag
    {
        return Tag::create(['project_id' => $project->id, 'name' => $name, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }
}
