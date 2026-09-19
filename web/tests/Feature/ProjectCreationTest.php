<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FinancialAccountType;
use App\Enums\ProjectRole;
use App\Models\BudgetTemplate;
use App\Models\MonthlyBudget;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_create_a_project_with_owner_membership_and_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/proyectos', [
            'name' => 'Economía familiar',
            'description' => 'Gastos e ingresos de casa',
            'color' => '#147d68',
            'icon' => 'home',
            'account_name' => 'Cuenta principal',
            'account_type' => FinancialAccountType::Checking->value,
            'initial_balance' => '1.234,56',
            'initial_balance_date' => '2026-09-15',
            'monthly_budget' => '1.500,00',
        ]);

        $project = Project::query()->sole();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'creator_user_id' => $user->id,
            'currency' => 'EUR',
            'locale' => 'es',
            'timezone' => 'Europe/Madrid',
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::Owner->value,
            'added_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('financial_accounts', [
            'project_id' => $project->id,
            'name' => 'Cuenta principal',
            'type' => FinancialAccountType::Checking->value,
            'initial_balance_cents' => 123456,
            'created_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('budget_templates', [
            'project_id' => $project->id,
            'effective_from_month' => now('Europe/Madrid')->startOfMonth()->toDateString(),
            'total_limit_cents' => 150000,
            'created_by_user_id' => $user->id,
        ]);
        $template = BudgetTemplate::query()->sole();
        $this->assertDatabaseHas('monthly_budgets', [
            'project_id' => $project->id,
            'month' => now('Europe/Madrid')->startOfMonth()->toDateString(),
            'total_limit_cents' => 150000,
            'source_template_id' => $template->id,
        ]);
        $budget = MonthlyBudget::query()->sole();
        $this->assertDatabaseHas('audit_logs', [
            'project_id' => $project->id,
            'subject_type' => 'budget',
            'subject_id' => $budget->id,
            'action' => 'created',
        ]);
    }

    public function test_project_creation_page_exposes_the_four_approved_steps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Identidad del proyecto',
                'Cuenta principal',
                'Presupuesto inicial',
                'Revisión final',
            ])
            ->assertSee('data-project-wizard', false)
            ->assertSee('data-review-budget', false);
    }

    public function test_an_invalid_initial_budget_does_not_create_partial_project_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Economía familiar',
            'description' => null,
            'color' => '#147d68',
            'icon' => 'home',
            'account_name' => 'Cuenta principal',
            'account_type' => FinancialAccountType::Checking->value,
            'initial_balance' => '0,00',
            'initial_balance_date' => '2026-09-15',
            'monthly_budget' => '-1,00',
        ])->assertSessionHasErrors('monthly_budget');

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('financial_accounts', 0);
        $this->assertDatabaseCount('monthly_budgets', 0);
        $this->assertDatabaseCount('budget_templates', 0);
    }

    public function test_a_project_is_visible_only_to_an_active_member(): void
    {
        $creator = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($creator, 'creator')->create();

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $creator->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $creator->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($creator)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->name);

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_removed_members_lose_access_immediately(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($creator, 'creator')->create();

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $creator->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $creator->id,
            'joined_at' => now(),
        ]);

        $membership = ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
            'added_by_user_id' => $creator->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)->get(route('projects.show', $project))->assertOk();

        $membership->update(['removed_at' => now()]);

        $this->actingAs($member)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_project_list_does_not_mix_other_users_projects(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $visible = Project::factory()->for($owner, 'creator')->create(['name' => 'Proyecto visible']);
        $hidden = Project::factory()->for($other, 'creator')->create(['name' => 'Proyecto oculto']);

        ProjectMember::create([
            'project_id' => $visible->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        ProjectMember::create([
            'project_id' => $hidden->id,
            'user_id' => $other->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $other->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Proyecto visible')
            ->assertDontSee('Proyecto oculto');
    }
}
