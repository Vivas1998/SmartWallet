<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtered_csv_uses_spanish_excel_format_and_all_active_filters(): void
    {
        [$project, $owner, , $account, $category, $subcategory] = $this->financialProject();
        $tag = $this->tag($project, $owner, 'Navidad');
        $included = $this->movement($project, $owner, $account, $category, $subcategory, '=Regalo familiar', '2026-12-24', 12345, 'Con ticket');
        $included->tags()->attach($tag);
        $this->movement($project, $owner, $account, $category, null, 'Supermercado', '2026-12-10', 5000);
        $this->movement($project, $owner, $account, $category, null, 'Regalo antiguo', '2025-12-24', 7000)->tags()->attach($tag);

        $response = $this->actingAs($owner)->get(route('movements.export', [
            'project' => $project, 'scope' => 'filtered', 'month' => '2026-12', 'type' => 'expense',
            'account' => $account->id, 'category' => $category->id, 'member' => $owner->id,
            'tag' => $tag->id, 'search' => 'Regalo',
        ]))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFProyecto;Tipo;", $csv);
        $this->assertStringContainsString("'=Regalo familiar", $csv);
        $this->assertStringContainsString('123,45', $csv);
        $this->assertStringContainsString('24/12/2026', $csv);
        $this->assertStringContainsString('Alimentación;Supermercado;Navidad;"Con ticket"', $csv);
        $this->assertStringNotContainsString('Regalo antiguo', $csv);
        $this->assertSame(2, substr_count($csv, "\r\n"));
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_complete_export_includes_all_active_years_but_never_the_trash(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $this->movement($project, $owner, $account, $category, null, 'Año anterior', '2025-01-02', 1000);
        $this->movement($project, $owner, $account, $category, null, 'Año actual', '2026-09-15', 2000);
        $trashed = $this->movement($project, $owner, $account, $category, null, 'En papelera', '2026-09-14', 3000);
        $trashed->update(['trashed_at' => now(), 'purge_at' => now()->addDays(30)]);

        $response = $this->actingAs($member)->get(route('movements.export', ['project' => $project, 'scope' => 'all']))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Año anterior', $csv);
        $this->assertStringContainsString('Año actual', $csv);
        $this->assertStringNotContainsString('En papelera', $csv);
    }

    public function test_only_owners_export_the_trash_and_outsiders_export_nothing(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $outsider = User::factory()->create();
        $trashed = $this->movement($project, $owner, $account, $category, null, 'Retirado', '2026-09-15', 3500);
        $trashed->update(['trashed_at' => now(), 'purge_at' => now()->addDays(30)]);

        $this->actingAs($member)->get(route('movements.trash.export', $project))->assertForbidden();
        $this->actingAs($outsider)->get(route('movements.export', ['project' => $project, 'scope' => 'all']))->assertForbidden();
        $csv = $this->actingAs($owner)->get(route('movements.trash.export', $project))->assertOk()->streamedContent();
        $this->assertStringContainsString('Retirado', $csv);
        $this->assertStringContainsString('Papelera', $csv);
        $this->assertStringContainsString('Eliminación definitiva', $csv);
    }

    public function test_custom_date_range_is_shared_by_the_list_and_filtered_export(): void
    {
        [$project, $owner, , $account, $category] = $this->financialProject();
        $this->movement($project, $owner, $account, $category, null, 'Dentro del rango', '2026-02-10', 1200);
        $this->movement($project, $owner, $account, $category, null, 'Fuera del rango', '2026-03-10', 1800);

        $this->actingAs($owner)->get(route('movements.index', ['project' => $project, 'from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertOk()->assertSee('Dentro del rango')->assertDontSee('Fuera del rango');
        $csv = $this->actingAs($owner)->get(route('movements.export', ['project' => $project, 'scope' => 'filtered', 'from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Dentro del rango', $csv);
        $this->assertStringNotContainsString('Fuera del rango', $csv);
    }

    /** @return array{Project, User, User, FinancialAccount, Category, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create(['name' => 'Casa familiar']);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => ProjectRole::Owner, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::Member, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        $account = FinancialAccount::create(['project_id' => $project->id, 'name' => 'Principal', 'type' => FinancialAccountType::Checking, 'initial_balance_cents' => 0, 'initial_balance_date' => '2025-01-01', 'position' => 10, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $category = Category::create(['project_id' => $project->id, 'type' => CategoryType::Expense, 'name' => 'Alimentación', 'color' => '#147d68', 'icon' => 'cart', 'position' => 10, 'is_initial' => false, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $subcategory = Category::create(['project_id' => $project->id, 'parent_id' => $category->id, 'type' => CategoryType::Expense, 'name' => 'Supermercado', 'color' => '#147d68', 'icon' => 'cart', 'position' => 10, 'is_initial' => false, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);

        return [$project, $owner, $member, $account, $category, $subcategory];
    }

    private function tag(Project $project, User $owner, string $name): Tag
    {
        return Tag::create(['project_id' => $project->id, 'name' => $name, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    private function movement(Project $project, User $owner, FinancialAccount $account, Category $category, ?Category $subcategory, string $concept, string $date, int $amount, ?string $notes = null): Movement
    {
        return Movement::create(['project_id' => $project->id, 'type' => MovementType::Expense, 'amount_cents' => $amount, 'occurred_on' => $date, 'concept' => $concept, 'category_id' => $category->id, 'subcategory_id' => $subcategory?->id, 'financial_account_id' => $account->id, 'paid_by_user_id' => $owner->id, 'notes' => $notes, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }
}
