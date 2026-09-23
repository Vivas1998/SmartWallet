<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\PlannedMovementStatus;
use App\Enums\ProjectRole;
use App\Enums\RecurrenceFrequency;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RecurrenceTemplate;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPageTest extends TestCase
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

    public function test_an_active_member_can_open_the_month_grid_and_mobile_agenda_with_only_calendar_events(): void
    {
        [$project, $owner, $member, $account, $expenseCategory] = $this->financialProject();
        $visible = $this->movement($project, $member, $account, $expenseCategory, '2026-09-15', 'Compra visible', true);
        $this->movement($project, $member, $account, $expenseCategory, '2026-09-15', 'Compra oculta', false);
        $plan = $this->plan($project, $member, $account, $expenseCategory, '2026-09-20', 'Seguro previsto');
        $recurrence = $this->recurrence($project, $owner, $account, $expenseCategory);

        $response = $this->actingAs($member)->get(route('calendar.index', [
            'project' => $project,
            'month' => '2026-09',
            'day' => '2026-09-15',
        ]));

        $response->assertOk()
            ->assertSee('Calendario financiero')
            ->assertSeeInOrder(['Movimientos', 'Calendario', 'Presupuesto'])
            ->assertSee('Vista mensual y detalle del día')
            ->assertSee('Agenda del mes')
            ->assertSee('Previsión del mes')
            ->assertSee('Disponible real')
            ->assertSee('Disponible estimado')
            ->assertSee('Los filtros inferiores solo modifican los eventos mostrados')
            ->assertSee('Compra visible')
            ->assertDontSee('Compra oculta')
            ->assertSee('Seguro previsto')
            ->assertSee('Cuota recurrente')
            ->assertSee(route('movements.edit', [$project, $visible]), false)
            ->assertSee(route('planned-movements.edit', [$project, $plan]), false)
            ->assertSee(route('recurrences.index', $project).'#recurrence-'.$recurrence->id, false)
            ->assertSee('Hoy')
            ->assertSee('Previsto');
    }

    public function test_calendar_filters_can_be_combined_without_losing_month_navigation(): void
    {
        [$project, , $member, $account, $expenseCategory] = $this->financialProject();
        $incomeCategory = $this->category($project, $member, CategoryType::Income, 'Nómina');
        $this->plan($project, $member, $account, $expenseCategory, '2026-09-10', 'Seguro vencido');
        $this->movement($project, $member, $account, $incomeCategory, '2026-09-15', 'Ingreso realizado', true, MovementType::Income);

        $query = [
            'project' => $project,
            'month' => '2026-09',
            'status' => 'overdue',
            'type' => 'expense',
            'account' => $account->id,
            'category' => $expenseCategory->id,
            'member' => $member->id,
        ];
        $response = $this->actingAs($member)->get(route('calendar.index', $query));

        $response->assertOk()
            ->assertSee('Seguro vencido')
            ->assertDontSee('Ingreso realizado')
            ->assertSeeInOrder(['<strong>1</strong>', 'evento visible'], false)
            ->assertSee('status=overdue', false)
            ->assertSee('type=expense', false)
            ->assertSee('account='.$account->id, false)
            ->assertSee('category='.$expenseCategory->id, false)
            ->assertSee('member='.$member->id, false);
    }

    public function test_calendar_respects_project_access_archiving_and_isolation(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $this->movement($project, $member, $account, $category, '2026-09-15', 'Dato familiar', true);
        [$otherProject, , $otherMember, $otherAccount, $otherCategory] = $this->financialProject();
        $this->movement($otherProject, $otherMember, $otherAccount, $otherCategory, '2026-09-15', 'Dato ajeno', true);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('calendar.index', $project))->assertForbidden();

        $response = $this->actingAs($member)->get(route('calendar.index', ['project' => $project, 'month' => '2026-09']));
        $response->assertOk()->assertSee('Dato familiar')->assertDontSee('Dato ajeno');

        $project->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);
        $this->actingAs($member)->get(route('calendar.index', ['project' => $project, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Proyecto archivado en modo de solo lectura')
            ->assertDontSee('+ Nueva planificación')
            ->assertSee('Dato familiar');
    }

    /** @return array{Project, User, User, FinancialAccount, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
            'added_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $account = FinancialAccount::create([
            'project_id' => $project->id,
            'name' => 'Cuenta principal',
            'type' => FinancialAccountType::Checking,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2024-01-01',
            'position' => 10,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $category = $this->category($project, $owner, CategoryType::Expense, 'Casa');

        return [$project, $owner, $member, $account, $category];
    }

    private function category(Project $project, User $user, CategoryType $type, string $name): Category
    {
        return Category::create([
            'project_id' => $project->id,
            'type' => $type,
            'name' => $name,
            'color' => '#147d68',
            'icon' => 'home',
            'position' => 10,
            'is_initial' => false,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
    }

    private function movement(
        Project $project,
        User $user,
        FinancialAccount $account,
        Category $category,
        string $date,
        string $concept,
        bool $visible,
        MovementType $type = MovementType::Expense,
    ): Movement {
        return Movement::create([
            'project_id' => $project->id,
            'type' => $type,
            'amount_cents' => 2500,
            'occurred_on' => $date,
            'concept' => $concept,
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $user->id,
            'show_in_calendar' => $visible,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
    }

    private function plan(
        Project $project,
        User $user,
        FinancialAccount $account,
        Category $category,
        string $date,
        string $concept,
    ): PlannedMovement {
        return PlannedMovement::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 5000,
            'due_on' => $date,
            'concept' => $concept,
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $user->id,
            'status' => PlannedMovementStatus::Pending,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
    }

    private function recurrence(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
    ): RecurrenceTemplate {
        return RecurrenceTemplate::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 1500,
            'concept' => 'Cuota recurrente',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'start_on' => '2026-09-25',
            'anchor_day' => 25,
            'next_occurrence_on' => '2026-09-25',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }
}
