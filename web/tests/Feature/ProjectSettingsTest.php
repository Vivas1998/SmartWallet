<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Recurrences\GenerateDueRecurrences;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\ProjectRole;
use App\Enums\RecurrenceFrequency;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RecurrenceTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_view_settings_but_only_owners_can_change_the_project(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);

        $this->actingAs($member)->get(route('projects.settings', $project))
            ->assertOk()
            ->assertSee('Solo los propietarios pueden modificarla');
        $this->actingAs($member)->patch(route('projects.update', $project), $this->identityData())
            ->assertForbidden();
        $this->actingAs($member)->post(route('projects.archive', $project))
            ->assertForbidden();
        $this->actingAs($outsider)->get(route('projects.settings', $project))
            ->assertForbidden();
    }

    public function test_an_owner_can_update_identity_without_changing_fixed_regional_data(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->actingAs($owner)->patch(route('projects.update', $project), array_merge($this->identityData(), [
            'name' => 'Navidad en familia',
            'description' => 'Presupuesto compartido actualizado',
            'currency' => 'USD',
            'locale' => 'en',
            'timezone' => 'UTC',
        ]))->assertRedirect(route('projects.settings', $project));

        $project->refresh();
        $this->assertSame('Navidad en familia', $project->name);
        $this->assertSame('Presupuesto compartido actualizado', $project->description);
        $this->assertSame('EUR', $project->currency);
        $this->assertSame('es', $project->locale);
        $this->assertSame('Europe/Madrid', $project->timezone);
        $this->assertDatabaseHas('audit_logs', [
            'project_id' => $project->id,
            'actor_user_id' => $owner->id,
            'subject_type' => 'project',
            'subject_id' => $project->id,
            'action' => 'updated',
        ]);
    }

    public function test_archiving_is_reversible_and_preserves_project_data(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $account = $this->account($project, $owner);

        $this->actingAs($owner)->post(route('projects.archive', $project))
            ->assertRedirect(route('projects.settings', $project));

        $project->refresh();
        $this->assertTrue($project->isArchived());
        $this->assertSame($owner->id, $project->archived_by_user_id);
        $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'project_id' => $project->id]);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_type' => 'project', 'action' => 'archived']);

        $this->actingAs($owner)->post(route('projects.restore', $project))
            ->assertRedirect(route('projects.settings', $project));

        $project->refresh();
        $this->assertFalse($project->isArchived());
        $this->assertNull($project->archived_by_user_id);
        $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'project_id' => $project->id]);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_type' => 'project', 'action' => 'reactivated']);
    }

    public function test_an_archived_project_is_read_only_but_remains_consultable(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $project->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertSee('modo de solo lectura');
        $this->actingAs($owner)->get(route('movements.index', $project))->assertOk();
        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2027-12']))->assertOk();
        $this->actingAs($owner)->get(route('reports.annual', ['project' => $project, 'year' => 2027]))->assertOk();
        $this->assertSame(0, $project->monthlyBudgets()->count(), 'Consultar un archivo no debe crear presupuestos nuevos.');

        $this->actingAs($owner)->get(route('movements.create', $project))->assertForbidden();
        $this->actingAs($owner)->put(route('budgets.update', $project), [
            'month' => '2027-12', 'total_limit' => '1000', 'scope' => 'month',
        ])->assertForbidden();
        $this->actingAs($owner)->patch(route('projects.update', $project), $this->identityData())
            ->assertForbidden();
    }

    public function test_only_an_owner_can_reactivate_an_archived_project(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);
        $project->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);

        $this->actingAs($member)->post(route('projects.restore', $project))->assertForbidden();
        $this->assertTrue($project->fresh()->isArchived());
    }

    public function test_recurring_movements_wait_while_archived_and_catch_up_after_reactivation(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $account = $this->account($project, $owner);
        $category = Category::create([
            'project_id' => $project->id,
            'type' => CategoryType::Expense,
            'name' => 'Casa',
            'color' => '#147d68',
            'icon' => 'home',
            'position' => 10,
            'is_initial' => false,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        RecurrenceTemplate::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 2500,
            'concept' => 'Cuota mensual',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'start_on' => '2026-07-01',
            'anchor_day' => 1,
            'next_occurrence_on' => '2026-07-01',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $project->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);

        $archivedResult = app(GenerateDueRecurrences::class)->handle(CarbonImmutable::parse('2026-09-01'));
        $this->assertSame(0, $archivedResult['generated']);
        $this->assertSame(0, $project->movements()->count());

        $this->actingAs($owner)->post(route('projects.restore', $project))->assertRedirect();
        $activeResult = app(GenerateDueRecurrences::class)->handle(CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(3, $activeResult['generated']);
        $this->assertSame(3, $project->movements()->count());
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
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'added_by_user_id' => $actor->id,
            'joined_at' => now(),
        ]);
    }

    private function account(Project $project, User $owner): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id,
            'name' => 'Principal',
            'type' => FinancialAccountType::Checking,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-01-01',
            'position' => 10,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    /** @return array<string, string> */
    private function identityData(): array
    {
        return [
            'name' => 'Proyecto actualizado',
            'description' => 'Descripción actualizada',
            'color' => '#315f87',
            'icon' => 'wallet',
        ];
    }
}
