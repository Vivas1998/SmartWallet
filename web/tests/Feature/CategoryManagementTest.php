<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_copies_the_complete_independent_initial_catalog(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/proyectos', [
            'name' => 'Economía familiar',
            'description' => null,
            'color' => '#147d68',
            'icon' => 'home',
            'account_name' => 'Cuenta principal',
            'account_type' => FinancialAccountType::Checking->value,
            'initial_balance' => '0',
            'initial_balance_date' => '2026-09-15',
            'monthly_budget' => '0',
        ])->assertRedirect();

        $project = Project::query()->sole();

        $this->assertSame(103, $project->categories()->count());
        $this->assertSame(16, $project->categories()->whereNull('parent_id')->where('type', 'expense')->count());
        $this->assertSame(5, $project->categories()->whereNull('parent_id')->where('type', 'income')->count());
        $this->assertSame(67, $project->categories()->whereNotNull('parent_id')->where('type', 'expense')->count());
        $this->assertSame(15, $project->categories()->whereNotNull('parent_id')->where('type', 'income')->count());

        $housing = $project->categories()->whereNull('parent_id')->where('name', 'Vivienda')->sole();
        $food = $project->categories()->whereNull('parent_id')->where('name', 'Alimentación')->sole();
        $this->assertSame(5, $housing->children()->count());
        $this->assertTrue($housing->is_initial);
        $this->assertSame('#B25900', $food->color);
    }

    public function test_an_owner_can_create_a_main_category_and_a_subcategory(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->actingAs($owner)->post(route('categories.store', $project), [
            'name' => 'Vehículo familiar',
            'type' => 'expense',
            'parent_id' => null,
            'color' => '#245678',
            'icon' => 'car',
        ])->assertRedirect(route('categories.index', $project));

        $parent = Category::query()->where('name', 'Vehículo familiar')->sole();

        $this->actingAs($owner)->post(route('categories.store', $project), [
            'name' => 'ITV',
            'type' => 'expense',
            'parent_id' => $parent->id,
            'color' => '#245678',
            'icon' => 'document',
        ])->assertRedirect(route('categories.index', $project));

        $this->assertDatabaseHas('categories', [
            'project_id' => $project->id,
            'parent_id' => $parent->id,
            'type' => 'expense',
            'name' => 'ITV',
            'is_initial' => false,
        ]);
    }

    public function test_a_subcategory_must_use_an_active_main_category_of_the_same_project_and_type(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject] = $this->projectWithOwner();
        $foreignParent = $this->category($otherProject, $owner, 'Ajena');
        $incomeParent = $this->category($project, $owner, 'Ingresos propios', CategoryType::Income);
        $expenseParent = $this->category($project, $owner, 'Gastos propios');
        $child = $this->category($project, $owner, 'Hija', CategoryType::Expense, $expenseParent->id);

        foreach ([$foreignParent->id, $incomeParent->id, $child->id] as $invalidParentId) {
            $this->actingAs($owner)->post(route('categories.store', $project), [
                'name' => 'Nueva '.$invalidParentId,
                'type' => 'expense',
                'parent_id' => $invalidParentId,
                'color' => '#147d68',
                'icon' => 'dot',
            ])->assertSessionHasErrors('parent_id');
        }
    }

    public function test_members_can_view_but_only_owners_can_manage_categories(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $member = User::factory()->create();
        $this->addMembership($project, $member, $owner, ProjectRole::Member);
        $category = $this->category($project, $owner, 'Visible');

        $this->actingAs($member)
            ->get(route('categories.index', $project))
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('Nueva categoría');

        $this->actingAs($member)
            ->post(route('categories.store', $project), [
                'name' => 'Prohibida',
                'type' => 'expense',
                'color' => '#147d68',
                'icon' => 'dot',
            ])->assertForbidden();

        $this->actingAs($member)
            ->post(route('categories.archive', [$project, $category]))
            ->assertForbidden();
    }

    public function test_an_owner_can_edit_archive_restore_and_reorder_categories(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $first = $this->category($project, $owner, 'Primera', position: 10);
        $second = $this->category($project, $owner, 'Segunda', position: 20);

        $this->actingAs($owner)
            ->patch(route('categories.update', [$project, $first]), [
                'name' => 'Primera editada',
                'color' => '#123456',
                'icon' => 'home',
            ])->assertRedirect(route('categories.index', $project));
        $this->assertSame('Primera editada', $first->refresh()->name);

        $this->actingAs($owner)
            ->post(route('categories.archive', [$project, $first]))
            ->assertRedirect(route('categories.index', $project));
        $this->assertNotNull($first->refresh()->archived_at);

        $this->actingAs($owner)
            ->post(route('categories.restore', [$project, $first]))
            ->assertRedirect(route('categories.index', $project));
        $this->assertNull($first->refresh()->archived_at);

        $this->actingAs($owner)
            ->post(route('categories.move', [$project, $second]), ['direction' => 'up'])
            ->assertRedirect(route('categories.index', $project));
        $this->assertLessThan($first->refresh()->position, $second->refresh()->position);
    }

    public function test_a_category_from_another_project_cannot_be_changed_through_the_current_project(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject, $otherOwner] = $this->projectWithOwner();
        $foreignCategory = $this->category($otherProject, $otherOwner, 'Ajena');

        $this->actingAs($owner)
            ->patch(route('categories.update', [$project, $foreignCategory]), [
                'name' => 'Alterada',
                'color' => '#123456',
                'icon' => 'dot',
            ])->assertNotFound();

        $this->assertSame('Ajena', $foreignCategory->refresh()->name);
    }

    /**
     * @return array{Project, User}
     */
    private function projectWithOwner(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        $this->addMembership($project, $owner, $owner, ProjectRole::Owner);

        return [$project, $owner];
    }

    private function addMembership(Project $project, User $user, User $actor, ProjectRole $role): ProjectMember
    {
        return ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'added_by_user_id' => $actor->id,
            'joined_at' => now(),
        ]);
    }

    private function category(
        Project $project,
        User $creator,
        string $name,
        CategoryType $type = CategoryType::Expense,
        ?int $parentId = null,
        int $position = 10,
    ): Category {
        return Category::create([
            'project_id' => $project->id,
            'parent_id' => $parentId,
            'type' => $type,
            'name' => $name,
            'color' => '#147d68',
            'icon' => 'dot',
            'position' => $position,
            'is_initial' => false,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);
    }
}
