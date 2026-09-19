<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Budgets\BuildMonthlyClosure;
use App\Actions\Movements\RebuildMovementEntries;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\ProjectRole;
use App\Models\AuditLog;
use App\Models\BudgetTemplate;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyClosureManagementTest extends TestCase
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

    public function test_members_can_review_a_recalculated_close_but_only_owners_can_allocate_leftover(): void
    {
        [$project, $owner, $member, $source, $savings] = $this->financialProject();
        $this->movement($project, $owner, $source, MovementType::Expense, 40000, 'Compra', '2026-08-10');
        $this->movement($project, $owner, $source, MovementType::Income, 20000, 'Ingreso', '2026-08-01');

        $this->actingAs($member)->get(route('budgets.closure', ['project' => $project, 'month' => '2026-08']))
            ->assertOk()
            ->assertSee('1.000,00 €')
            ->assertSee('400,00 €')
            ->assertSee('600,00 €')
            ->assertSee('200,00 €')
            ->assertSee('800,00 €')
            ->assertSee('Solo los propietarios pueden destinar el sobrante')
            ->assertDontSee('Crear transferencia');

        $this->actingAs($member)->post(route('budgets.closure.store', $project), $this->allocationData($source, $savings, 10000))
            ->assertForbidden();
        $this->assertDatabaseCount('monthly_leftover_allocations', 0);
    }

    public function test_an_owner_allocates_leftover_to_savings_and_investment_without_changing_the_month_result(): void
    {
        [$project, $owner, , $source, $savings, $investment] = $this->financialProject();
        $this->movement($project, $owner, $source, MovementType::Expense, 40000, 'Compra', '2026-08-10');
        $goal = SavingsGoal::create([
            'project_id' => $project->id,
            'financial_account_id' => $savings->id,
            'name' => 'Colchón',
            'target_amount_cents' => 100000,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('budgets.closure.store', $project), [
            ...$this->allocationData($source, $savings, 25000),
            'savings_goal_id' => $goal->id,
        ])->assertRedirect(route('budgets.closure', ['project' => $project, 'month' => '2026-08']));
        $this->actingAs($owner)->post(route('budgets.closure.store', $project), $this->allocationData($source, $investment, 10000))
            ->assertRedirect();

        $allocations = $project->leftoverAllocations()->with('movement.entries')->orderBy('id')->get();
        $this->assertCount(2, $allocations);
        $this->assertSame('2026-08-01', $allocations->first()->budget_month->toDateString());
        $this->assertSame(MovementType::Transfer, $allocations->first()->movement->type);
        $this->assertSame(MovementType::InvestmentContribution, $allocations->last()->movement->type);
        $this->assertSame([-25000, 25000], $allocations->first()->movement->entries->sortBy('signed_amount_cents')->pluck('signed_amount_cents')->all());
        $this->assertDatabaseHas('goal_allocations', ['movement_id' => $allocations->first()->movement_id, 'savings_goal_id' => $goal->id, 'direction' => 'contribution']);
        $this->assertSame(25000, $goal->fresh()->currentAmountCents());

        $summary = app(BuildMonthlyClosure::class)->handle($project, CarbonImmutable::parse('2026-08-01'));
        $this->assertSame(60000, $summary['remaining_cents']);
        $this->assertSame(35000, $summary['allocated_cents']);
        $this->assertSame(25000, $summary['allocated_savings_cents']);
        $this->assertSame(10000, $summary['allocated_investment_cents']);
        $this->assertSame(25000, $summary['available_to_allocate_cents']);
        $audit = AuditLog::query()->where('subject_id', $allocations->first()->movement_id)->where('subject_type', 'movement')->firstOrFail();
        $this->assertSame('2026-08-01', $audit->after_values['leftover_budget_month']);
    }

    public function test_allocation_requires_a_finished_month_available_leftover_and_real_account_balance(): void
    {
        [$project, $owner, , $source, $savings] = $this->financialProject(50000);
        $this->movement($project, $owner, $source, MovementType::Expense, 40000, 'Compra', '2026-08-10');
        $returnTo = route('budgets.closure', ['project' => $project, 'month' => '2026-08']);

        $this->actingAs($owner)->from($returnTo)->post(route('budgets.closure.store', $project), $this->allocationData($source, $savings, 70000))
            ->assertSessionHasErrors('amount');
        $this->actingAs($owner)->from($returnTo)->post(route('budgets.closure.store', $project), $this->allocationData($source, $savings, 20000))
            ->assertSessionHasErrors('financial_account_id');
        $this->actingAs($owner)->from($returnTo)->post(route('budgets.closure.store', $project), [
            ...$this->allocationData($source, $savings, 5000),
            'month' => '2026-09',
        ])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('monthly_leftover_allocations', 0);
    }

    public function test_historical_corrections_warn_about_excess_allocations_without_reverting_them(): void
    {
        [$project, $owner, , $source, $savings] = $this->financialProject();
        $this->movement($project, $owner, $source, MovementType::Expense, 40000, 'Compra', '2026-08-10');
        $this->actingAs($owner)->post(route('budgets.closure.store', $project), $this->allocationData($source, $savings, 50000));
        $allocation = $project->leftoverAllocations()->with('movement')->firstOrFail();
        $this->movement($project, $owner, $source, MovementType::Expense, 20000, 'Corrección', '2026-08-20');

        $summary = app(BuildMonthlyClosure::class)->handle($project, CarbonImmutable::parse('2026-08-01'));
        $this->assertSame(40000, $summary['remaining_cents']);
        $this->assertSame(50000, $summary['allocated_cents']);
        $this->assertSame(10000, $summary['overallocated_cents']);
        $this->actingAs($owner)->get(route('budgets.closure', ['project' => $project, 'month' => '2026-08']))
            ->assertOk()->assertSee('Se destinó más dinero')->assertSee('100,00 €');

        $this->actingAs($owner)->delete(route('movements.destroy', [$project, $allocation->movement]))->assertRedirect();
        $recalculated = app(BuildMonthlyClosure::class)->handle($project, CarbonImmutable::parse('2026-08-01'));
        $this->assertSame(0, $recalculated['allocated_cents']);
        $this->assertSame(40000, $recalculated['available_to_allocate_cents']);
        $this->assertDatabaseHas('monthly_leftover_allocations', ['movement_id' => $allocation->movement_id]);
    }

    public function test_a_member_can_edit_an_allocation_without_exceeding_the_original_month_leftover(): void
    {
        [$project, $owner, $member, $source, $savings] = $this->financialProject();
        $this->movement($project, $owner, $source, MovementType::Expense, 40000, 'Compra', '2026-08-10');
        $this->actingAs($owner)->post(route('budgets.closure.store', $project), $this->allocationData($source, $savings, 50000));
        $movement = $project->leftoverAllocations()->with('movement')->firstOrFail()->movement;
        $editData = [
            'amount' => '700,00',
            'occurred_on' => '2026-09-15',
            'concept' => $movement->concept,
            'financial_account_id' => $source->id,
            'destination_account_id' => $savings->id,
        ];

        $this->actingAs($member)->from(route('movements.edit', [$project, $movement]))
            ->patch(route('movements.update', [$project, $movement]), $editData)
            ->assertSessionHasErrors('amount');
        $this->actingAs($member)->patch(route('movements.update', [$project, $movement]), [
            ...$editData,
            'amount' => '600,00',
        ])->assertRedirect(route('budgets.closure', ['project' => $project, 'month' => '2026-08']));

        $summary = app(BuildMonthlyClosure::class)->handle($project, CarbonImmutable::parse('2026-08-01'));
        $this->assertSame(60000, $summary['remaining_cents']);
        $this->assertSame(60000, $summary['allocated_cents']);
        $this->assertSame(0, $summary['available_to_allocate_cents']);
        $this->assertSame(0, $source->fresh()->currentBalanceCents());
    }

    /** @return array{Project, User, User, FinancialAccount, FinancialAccount, FinancialAccount} */
    private function financialProject(int $sourceBalanceCents = 100000): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => ProjectRole::Owner, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::Member, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        BudgetTemplate::create(['project_id' => $project->id, 'effective_from_month' => '2026-08-01', 'total_limit_cents' => 100000, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $source = $this->account($project, $owner, 'Principal', FinancialAccountType::Checking, $sourceBalanceCents);
        $savings = $this->account($project, $owner, 'Ahorro', FinancialAccountType::Savings);
        $investment = $this->account($project, $owner, 'Inversión', FinancialAccountType::ExternalInvestment);

        return [$project, $owner, $member, $source, $savings, $investment];
    }

    private function account(Project $project, User $owner, string $name, FinancialAccountType $type, int $initialBalanceCents = 0): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id,
            'name' => $name,
            'type' => $type,
            'initial_balance_cents' => $initialBalanceCents,
            'initial_balance_date' => '2026-01-01',
            'position' => 10,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    private function movement(Project $project, User $owner, FinancialAccount $account, MovementType $type, int $amountCents, string $concept, string $date): Movement
    {
        $movement = Movement::create([
            'project_id' => $project->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'occurred_on' => $date,
            'concept' => $concept,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        app(RebuildMovementEntries::class)->handle($movement);

        return $movement;
    }

    /** @return array<string, mixed> */
    private function allocationData(FinancialAccount $source, FinancialAccount $destination, int $amountCents): array
    {
        return [
            'month' => '2026-08',
            'amount' => number_format($amountCents / 100, 2, ',', ''),
            'financial_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'occurred_on' => '2026-09-15',
        ];
    }
}
