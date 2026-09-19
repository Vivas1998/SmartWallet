<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FinancialAccountType;
use App\Enums\ProjectRole;
use App\Models\FinancialAccount;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsGoalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_owner_creates_a_goal_and_a_member_can_contribute_and_withdraw(): void
    {
        [$project, $owner, $source, $savings] = $this->projectWithSavings();
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);

        $this->actingAs($owner)->post(route('savings-goals.store', $project), [
            'name' => 'Fondo de emergencia', 'target_amount' => '1.000', 'target_date' => '2027-12-31',
            'financial_account_id' => $savings->id,
        ])->assertRedirect();
        $goal = $project->savingsGoals()->firstOrFail();
        $this->actingAs($member)->get(route('savings-goals.contribute', [$project, $goal]))->assertOk();

        $this->actingAs($member)->post(route('movements.transfer.store', $project), [
            'amount' => '250', 'occurred_on' => '2026-09-15', 'concept' => 'Primera aportación',
            'financial_account_id' => $source->id, 'destination_account_id' => $savings->id,
            'savings_goal_id' => $goal->id, 'goal_direction' => 'contribution',
        ])->assertRedirect();
        $this->actingAs($member)->post(route('movements.transfer.store', $project), [
            'amount' => '50', 'occurred_on' => '2026-09-15', 'concept' => 'Retirada puntual',
            'financial_account_id' => $savings->id, 'destination_account_id' => $source->id,
            'savings_goal_id' => $goal->id, 'goal_direction' => 'withdrawal',
        ])->assertRedirect();

        $this->assertSame(20000, $goal->fresh()->currentAmountCents());
        $this->actingAs($member)->get(route('savings-goals.index', $project))
            ->assertOk()->assertSee('200,00 €')->assertSee('20%')
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuenow="20"', false);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_type' => 'goal', 'action' => 'created']);
    }

    public function test_goal_progress_follows_edits_trash_and_restoration_of_the_real_transfer(): void
    {
        [$project, $owner, $source, $savings] = $this->projectWithSavings();
        $goal = $this->goal($project, $owner, $savings);
        $this->actingAs($owner)->post(route('movements.transfer.store', $project), [
            'amount' => '100', 'occurred_on' => '2026-09-15', 'concept' => 'Aportación',
            'financial_account_id' => $source->id, 'destination_account_id' => $savings->id,
            'savings_goal_id' => $goal->id, 'goal_direction' => 'contribution',
        ])->assertRedirect();
        $movement = $project->movements()->firstOrFail();

        $this->actingAs($owner)->patch(route('movements.update', [$project, $movement]), [
            'amount' => '150', 'occurred_on' => '2026-09-15', 'concept' => 'Aportación corregida',
            'financial_account_id' => $source->id, 'destination_account_id' => $savings->id,
            'savings_goal_id' => $goal->id, 'goal_direction' => 'contribution',
        ])->assertRedirect();
        $this->assertSame(15000, $goal->fresh()->currentAmountCents());

        $this->actingAs($owner)->delete(route('movements.destroy', [$project, $movement]))->assertRedirect();
        $this->assertSame(0, $goal->fresh()->currentAmountCents());
        $this->actingAs($owner)->post(route('movements.restore', [$project, $movement]))->assertRedirect();
        $this->assertSame(15000, $goal->fresh()->currentAmountCents());
    }

    public function test_members_cannot_manage_goals_and_cross_project_links_are_rejected(): void
    {
        [$project, $owner, $source, $savings] = $this->projectWithSavings();
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);
        $goal = $this->goal($project, $owner, $savings);
        [$otherProject, $otherOwner, , $otherSavings] = $this->projectWithSavings();
        $otherGoal = $this->goal($otherProject, $otherOwner, $otherSavings);

        $this->actingAs($member)->post(route('savings-goals.store', $project), [
            'name' => 'No permitido', 'target_amount' => '500', 'financial_account_id' => $savings->id,
        ])->assertForbidden();
        $this->actingAs($member)->post(route('movements.transfer.store', $project), [
            'amount' => '100', 'occurred_on' => '2026-09-15', 'concept' => 'Cruce inválido',
            'financial_account_id' => $source->id, 'destination_account_id' => $savings->id,
            'savings_goal_id' => $otherGoal->id, 'goal_direction' => 'contribution',
        ])->assertSessionHasErrors('savings_goal_id');
        $this->assertSame(0, $goal->allocations()->count());
    }

    /** @return array{Project, User, FinancialAccount, FinancialAccount} */
    private function projectWithSavings(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        $this->membership($project, $owner, $owner, ProjectRole::Owner);
        $source = $this->account($project, $owner, FinancialAccountType::Checking, 'Principal');
        $savings = $this->account($project, $owner, FinancialAccountType::Savings, 'Ahorro');

        return [$project, $owner, $source, $savings];
    }

    private function goal(Project $project, User $owner, FinancialAccount $account): SavingsGoal
    {
        return SavingsGoal::create([
            'project_id' => $project->id, 'financial_account_id' => $account->id, 'name' => 'Objetivo',
            'target_amount_cents' => 100000, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
    }

    private function account(Project $project, User $owner, FinancialAccountType $type, string $name): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id, 'name' => $name, 'type' => $type, 'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-01-01', 'position' => 10, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
    }

    private function membership(Project $project, User $user, User $actor, ProjectRole $role): void
    {
        ProjectMember::create([
            'project_id' => $project->id, 'user_id' => $user->id, 'role' => $role,
            'added_by_user_id' => $actor->id, 'joined_at' => now(),
        ]);
    }
}
