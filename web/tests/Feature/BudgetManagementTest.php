<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\MovementType;
use App\Enums\ProjectRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Movement;
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
        $rent = $this->category($project, $owner, 'Alquiler', $housing);

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1.000,00',
            'limits' => [$housing->id => '450,50', $rent->id => '300'],
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
        $this->assertDatabaseHas('monthly_budget_limits', [
            'category_id' => $rent->id,
            'limit_cents' => 30000,
        ]);
        $this->assertDatabaseHas('budget_template_limits', [
            'category_id' => $rent->id,
            'limit_cents' => 30000,
        ]);
        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2026-10']))
            ->assertOk()
            ->assertSee('Límites por categoría y subcategoría')
            ->assertSee('Alquiler')
            ->assertSee('name="limits['.$rent->id.']"', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuenow="0"', false);

        $audit = AuditLog::query()
            ->where('project_id', $project->id)
            ->where('subject_type', 'budget')
            ->where('action', 'updated')
            ->sole();
        $this->assertSame(30000, $audit->after_values['limits'][(string) $rent->id]);
    }

    public function test_a_future_month_uses_the_active_template_without_carrying_leftover_money(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');
        $rent = $this->category($project, $owner, 'Alquiler', $housing);

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1000',
            'limits' => [$housing->id => '400', $rent->id => '250'],
            'scope' => 'future',
        ]);

        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2026-11']))->assertOk();

        $november = $project->monthlyBudgets()->whereDate('month', '2026-11-01')->sole();
        $this->assertSame(100000, $november->total_limit_cents);
        $this->assertSame(40000, $november->limits()->where('category_id', $housing->id)->value('limit_cents'));
        $this->assertSame(25000, $november->limits()->where('category_id', $rent->id)->value('limit_cents'));
    }

    public function test_a_one_month_exception_does_not_change_the_following_months(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');
        $rent = $this->category($project, $owner, 'Alquiler', $housing);

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10', 'total_limit' => '1000', 'limits' => [$housing->id => '400', $rent->id => '250'], 'scope' => 'future',
        ]);
        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-12', 'total_limit' => '1500', 'limits' => [$housing->id => '600', $rent->id => '500'], 'scope' => 'month',
        ]);
        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2027-01']))->assertOk();

        $january = $project->monthlyBudgets()->whereDate('month', '2027-01-01')->sole();
        $this->assertSame(100000, $january->total_limit_cents);
        $this->assertSame(40000, $january->limits()->where('category_id', $housing->id)->value('limit_cents'));
        $this->assertSame(25000, $january->limits()->where('category_id', $rent->id)->value('limit_cents'));
    }

    public function test_subcategory_limits_cannot_exceed_their_main_category_limit(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');
        $rent = $this->category($project, $owner, 'Alquiler', $housing);
        $utilities = $this->category($project, $owner, 'Suministros', $housing);

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1000',
            'limits' => [$housing->id => '400', $rent->id => '250', $utilities->id => '200'],
            'scope' => 'future',
        ])->assertSessionHasErrors('limits.'.$housing->id);

        $this->assertDatabaseMissing('monthly_budgets', [
            'project_id' => $project->id,
            'month' => '2026-10-01',
        ]);
    }

    public function test_expenses_and_refunds_update_main_and_subcategory_progress_separately(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $food = $this->category($project, $owner, 'Alimentación');
        $supermarket = $this->category($project, $owner, 'Supermercado', $food);

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1200',
            'limits' => [$food->id => '1000', $supermarket->id => '750'],
            'scope' => 'future',
        ]);

        $this->movement($project, $owner, MovementType::Expense, 70000, $food, $supermarket, 'Compra semanal');
        $this->movement($project, $owner, MovementType::Refund, 10000, $food, $supermarket, 'Devolución compra');
        $this->movement($project, $owner, MovementType::Expense, 20000, $food, null, 'Comida');

        $this->actingAs($owner)
            ->get(route('budgets.index', ['project' => $project, 'month' => '2026-10']))
            ->assertOk()
            ->assertSee('800,00 € gastados')
            ->assertSee('600,00 € gastados')
            ->assertSee('Sin subcategoría')
            ->assertSee('200,00 €')
            ->assertSee('data-budget-category-state="warning"', false)
            ->assertSee('data-budget-subcategory-state="warning"', false)
            ->assertSee('data-open-state="open"', false);
    }

    public function test_archived_subcategory_limits_remain_historical_but_are_not_copied_or_editable(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');
        $rent = $this->category($project, $owner, 'Alquiler', $housing);

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-10',
            'total_limit' => '1000',
            'limits' => [$housing->id => '600', $rent->id => '500'],
            'scope' => 'future',
        ]);

        $rent->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get(route('budgets.index', ['project' => $project, 'month' => '2026-10']))
            ->assertOk()
            ->assertSee('Alquiler')
            ->assertSee('Archivada')
            ->assertDontSee('name="limits['.$rent->id.']"', false);

        $this->actingAs($owner)
            ->get(route('budgets.index', ['project' => $project, 'month' => '2026-11']))
            ->assertOk();

        $november = $project->monthlyBudgets()->whereDate('month', '2026-11-01')->sole();
        $this->assertFalse($november->limits()->where('category_id', $rent->id)->exists());

        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2026-11',
            'total_limit' => '1000',
            'limits' => [$housing->id => '600', $rent->id => '500'],
            'scope' => 'month',
        ])->assertSessionHasErrors('limits');
    }

    public function test_limits_reject_categories_that_are_not_active_project_expenses(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject, $otherOwner] = $this->projectWithOwner();
        $income = $this->category($project, $owner, 'Nómina', null, CategoryType::Income);
        $foreign = $this->category($otherProject, $otherOwner, 'Otra vivienda');

        foreach ([$income, $foreign] as $invalidCategory) {
            $this->actingAs($owner)->put(route('budgets.update', $project), [
                'month' => '2026-10',
                'total_limit' => '1000',
                'limits' => [$invalidCategory->id => '100'],
                'scope' => 'month',
            ])->assertSessionHasErrors('limits');
        }
    }

    public function test_members_can_view_but_only_owners_can_change_budgets(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $housing = $this->category($project, $owner, 'Vivienda');
        $this->category($project, $owner, 'Alquiler', $housing);
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);

        $this->actingAs($member)->get(route('budgets.index', $project))
            ->assertOk()
            ->assertSee('Alquiler')
            ->assertDontSee('Guardar presupuesto')
            ->assertDontSee('name="limits[', false);
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

    private function category(
        Project $project,
        User $owner,
        string $name,
        ?Category $parent = null,
        CategoryType $type = CategoryType::Expense,
    ): Category {
        return Category::create([
            'project_id' => $project->id, 'parent_id' => $parent?->id, 'type' => $type, 'name' => $name,
            'color' => '#147d68', 'icon' => 'home', 'position' => 10, 'is_initial' => false,
            'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
    }

    private function movement(
        Project $project,
        User $owner,
        MovementType $type,
        int $amountCents,
        Category $category,
        ?Category $subcategory,
        string $concept,
    ): Movement {
        return Movement::create([
            'project_id' => $project->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'occurred_on' => '2026-10-15',
            'concept' => $concept,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory?->id,
            'paid_by_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }
}
