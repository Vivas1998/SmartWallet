<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_set_a_monthly_budget_and_category_limits(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1.000,00',
            'limits' => [$housing->id => '450,50'],
            'scope' => 'future',
        ])->assertRedirect(route('budgets.index', ['project' => $project, 'month' => '2026-10']));

        $this->assertDatabaseHas('monthly_budgets', [
            'project_id' => $project->id,
            'month' => '2026-10-01',
            'total_limit_cents' => 100000,
        ]);
        $this->assertDatabaseHas('monthly_budget_limits', [
            'category_id' => $housing->id,
            'limit_cents' => 45050,
        ]);
        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2026-10']))
            ->assertOk()
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuenow="0"', false);
    }

    public function test_a_future_month_uses_the_active_template_without_carrying_leftover_money(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1000',
            'limits' => [$housing->id => '400'],
            'scope' => 'future',
        ]);

        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2026-11']))->assertOk();

        $november = $project->monthlyBudgets()->whereDate('month', '2026-11-01')->sole();
        $this->assertSame(100000, $november->total_limit_cents);
        $this->assertSame(40000, $november->limits()->where('category_id', $housing->id)->value('limit_cents'));
    }

    public function test_a_one_month_exception_does_not_change_the_following_months(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10', 'total_limit' => '1000', 'limits' => [$housing->id => '400'], 'scope' => 'future',
        ]);
        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-12', 'total_limit' => '1500', 'limits' => [$housing->id => '600'], 'scope' => 'month',
        ]);
        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2027-01']))->assertOk();

        $january = $project->monthlyBudgets()->whereDate('month', '2027-01-01')->sole();
        $this->assertSame(100000, $january->total_limit_cents);
        $this->assertSame(40000, $january->limits()->where('category_id', $housing->id)->value('limit_cents'));
    }

    public function test_members_can_view_but_only_owners_can_change_budgets(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);

        $this->actingAs($member)->get(route('budgets.index', $project))->assertOk()->assertDontSee('Guardar presupuesto');
        $this->actingAs($member)->put(route('budgets.update', $project), [
            'month' => '2026-10', 'total_limit' => '1000', 'scope' => 'future',
        ])->assertForbidden();
    }

    /** @return array{Project, User} */
    private function projectWithOwner(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        $this->membership($project, $owner, $owner, ProjectRole::Owner);

        return [$project, $owner];
    }

    private function membership(Project $project, User $user, User $actor, ProjectRole $role): void
    {
        ProjectMember::create([
            'project_id' => $project->id, 'user_id' => $user->id, 'role' => $role,
            'added_by_user_id' => $actor->id, 'joined_at' => now(),
        ]);
    }

    private function category(Project $project, User $owner, string $name): Category
    {
        return Category::create([
            'project_id' => $project->id, 'type' => CategoryType::Expense, 'name' => $name,
            'color' => '#147d68', 'icon' => 'home', 'position' => 10, 'is_initial' => false,
            'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
    }
}
