<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Projects\BuildProjectCardBudgetSummaries;
use App\Enums\MovementType;
use App\Enums\ProjectRole;
use App\Models\AuditLog;
use App\Models\BudgetTemplate;
use App\Models\MonthlyBudget;
use App\Models\Movement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_shows_each_projects_last_activity_and_a_scoped_recent_feed(): void
    {
        $owner = User::factory()->create(['name' => 'Lucía Martín']);
        $other = User::factory()->create(['name' => 'Persona ajena']);
        $family = $this->projectWithOwner($owner, 'Economía familiar');
        $personal = $this->projectWithOwner($owner, 'Finanzas personales');
        $hidden = $this->projectWithOwner($other, 'Proyecto privado ajeno');

        AuditLog::create([
            'project_id' => $family->id,
            'actor_user_id' => $owner->id,
            'subject_type' => 'movement',
            'subject_id' => 10,
            'action' => 'updated',
            'created_at' => '2026-09-12 10:00:00',
        ]);
        AuditLog::create([
            'project_id' => $personal->id,
            'actor_user_id' => null,
            'subject_type' => 'recurrence',
            'subject_id' => 20,
            'action' => 'generated',
            'created_at' => '2026-09-13 10:00:00',
        ]);
        AuditLog::create([
            'project_id' => $hidden->id,
            'actor_user_id' => $other->id,
            'subject_type' => 'movement',
            'subject_id' => 30,
            'action' => 'created',
            'created_at' => '2026-09-14 10:00:00',
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Actividad reciente')
            ->assertSee('Última actividad')
            ->assertSee('Lucía Martín')
            ->assertSee('modificó un movimiento')
            ->assertSee('Sistema')
            ->assertSee('generó automáticamente una serie recurrente')
            ->assertSeeInOrder(['Finanzas personales', 'Economía familiar'])
            ->assertDontSee('Proyecto privado ajeno')
            ->assertDontSee('Persona ajena');
    }

    public function test_project_cards_show_balanced_current_month_budgets_without_creating_records(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'Europe/Madrid'));

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $normal = $this->projectWithOwner($owner, 'Casa');
        $warning = $this->projectWithOwner($owner, 'Navidad');
        $danger = $this->projectWithOwner($owner, 'Reforma');
        $empty = $this->projectWithOwner($owner, 'Sin planificar');
        $archivedWithoutMonth = $this->projectWithOwner($owner, 'Archivo sin mes', ['archived_at' => now()]);
        $archivedWithMonth = $this->projectWithOwner($owner, 'Archivo con mes', ['archived_at' => now()]);
        $hidden = $this->projectWithOwner($other, 'Presupuesto privado');

        $this->template($normal, $owner, 100000);
        $this->template($archivedWithoutMonth, $owner, 90000);
        MonthlyBudget::create(['project_id' => $warning->id, 'month' => '2026-09-01', 'total_limit_cents' => 100000]);
        MonthlyBudget::create(['project_id' => $danger->id, 'month' => '2026-09-01', 'total_limit_cents' => 100000]);
        MonthlyBudget::create(['project_id' => $archivedWithMonth->id, 'month' => '2026-09-01', 'total_limit_cents' => 50000]);

        $this->movement($normal, $owner, MovementType::Expense, 70000);
        $this->movement($normal, $owner, MovementType::Refund, 10000);
        $this->movement($warning, $owner, MovementType::Expense, 85000);
        $this->movement($danger, $owner, MovementType::Expense, 115000);
        $this->movement($archivedWithMonth, $owner, MovementType::Expense, 20000);
        $this->movement($hidden, $other, MovementType::Expense, 999999);

        $budgetCount = MonthlyBudget::query()->count();

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-budget-summary="'.$normal->id.':normal"', false)
            ->assertSee('400,00 €')
            ->assertSee('600,00 € gastados')
            ->assertSee('1.000,00 € de presupuesto')
            ->assertSee('60%')
            ->assertSee('data-budget-summary="'.$warning->id.':warning"', false)
            ->assertSee('150,00 €')
            ->assertSee('85%')
            ->assertSee('data-budget-summary="'.$danger->id.':danger"', false)
            ->assertSee('-150,00 €')
            ->assertSee('115%')
            ->assertSee('data-budget-summary="'.$empty->id.':empty"', false)
            ->assertSee('data-budget-summary="'.$archivedWithoutMonth->id.':empty"', false)
            ->assertSee('data-budget-summary="'.$archivedWithMonth->id.':normal"', false)
            ->assertSee('Sin presupuesto para este mes')
            ->assertDontSee('Presupuesto privado')
            ->assertDontSee('9.999,99 €');

        $this->assertSame($budgetCount, MonthlyBudget::query()->count(), 'Consultar Mis proyectos no debe crear presupuestos mensuales.');
    }

    public function test_project_budget_cards_use_a_fixed_number_of_queries(): void
    {
        $owner = User::factory()->create();
        $projects = collect(range(1, 6))
            ->map(fn (int $number): Project => $this->projectWithOwner($owner, 'Proyecto '.$number));
        $month = CarbonImmutable::parse('2026-09-01', 'Europe/Madrid');
        $action = app(BuildProjectCardBudgetSummaries::class);

        DB::enableQueryLog();
        $action->handle(new EloquentCollection([$projects->first()]), $month);
        $singleProjectQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        $action->handle(new EloquentCollection($projects->all()), $month);
        $multipleProjectQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(4, $singleProjectQueries);
        $this->assertSame($singleProjectQueries, $multipleProjectQueries);
    }

    public function test_project_switcher_lists_only_active_memberships_and_marks_archived_projects(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $current = $this->projectWithOwner($owner, 'Casa');
        $archived = $this->projectWithOwner($owner, 'Vacaciones', ['archived_at' => now()]);
        $hidden = $this->projectWithOwner($other, 'Negocio ajeno');
        $removed = $this->projectWithOwner($owner, 'Proyecto retirado');
        $removed->memberships()->where('user_id', $owner->id)->update(['removed_at' => now()]);

        $response = $this->actingAs($owner)->get(route('projects.settings', $current));

        $response->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content" tabindex="-1"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Proyecto actual')
            ->assertSee('data-project-switcher', false)
            ->assertSee(route('projects.show', $current), false)
            ->assertSee(route('projects.show', $archived), false)
            ->assertSee('Vacaciones (archivado)')
            ->assertDontSee('Negocio ajeno')
            ->assertDontSee('Proyecto retirado')
            ->assertDontSee(route('projects.show', $hidden), false)
            ->assertSee('Ver mis proyectos');
    }

    /** @param array<string, mixed> $attributes */
    private function projectWithOwner(User $owner, string $name, array $attributes = []): Project
    {
        $project = Project::factory()->for($owner, 'creator')->create(array_merge([
            'name' => $name,
        ], $attributes));

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        return $project;
    }

    private function template(Project $project, User $owner, int $totalLimitCents): BudgetTemplate
    {
        return BudgetTemplate::create([
            'project_id' => $project->id,
            'effective_from_month' => '2026-01-01',
            'total_limit_cents' => $totalLimitCents,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    private function movement(Project $project, User $user, MovementType $type, int $amountCents): Movement
    {
        return Movement::create([
            'project_id' => $project->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'occurred_on' => '2026-09-10',
            'concept' => $type->label().' de prueba',
            'paid_by_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
    }
}
