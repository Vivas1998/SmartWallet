<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Audit\RecordProjectAudit;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\PlannedMovementStatus;
use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CalendarFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_movements_are_hidden_from_the_calendar_by_default(): void
    {
        [$project, $owner, , $account, $category] = $this->financialProject();

        $hidden = $this->movement($project, $owner, $account, $category);
        $visible = $this->movement($project, $owner, $account, $category, ['show_in_calendar' => true]);

        $this->assertFalse($hidden->fresh()->show_in_calendar);
        $this->assertTrue($visible->fresh()->show_in_calendar);
        $this->assertFalse($hidden->fresh()->auditSnapshot()['show_in_calendar']);
        $this->assertTrue($visible->fresh()->auditSnapshot()['show_in_calendar']);
    }

    public function test_a_planned_movement_keeps_calendar_data_relations_and_audit_history(): void
    {
        [$project, $owner, , $account, $category] = $this->financialProject();
        $tag = Tag::create([
            'project_id' => $project->id,
            'name' => 'Vencimiento',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $plan = PlannedMovement::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 8990,
            'due_on' => '2026-10-15',
            'concept' => 'Seguro del hogar',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'status' => PlannedMovementStatus::Pending,
            'notes' => 'Revisar la renovación.',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $plan->tags()->attach($tag);

        $this->assertTrue($project->plannedMovements->contains($plan));
        $this->assertTrue($plan->tags->contains($tag));
        $this->assertTrue($tag->plannedMovements->contains($plan));
        $this->assertTrue($plan->account->is($account));
        $this->assertTrue($plan->category->is($category));
        $this->assertSame(PlannedMovementStatus::Pending, $plan->status);
        $this->assertSame('89,90 €', $plan->formattedAmount());
        $this->assertSame([$tag->id], $plan->auditSnapshot()['tag_ids']);

        $before = $plan->auditSnapshot();
        $movement = $this->movement($project, $owner, $account, $category, [
            'amount_cents' => 8990,
            'occurred_on' => '2026-10-17',
            'concept' => 'Seguro del hogar',
        ]);
        $plan->update([
            'status' => PlannedMovementStatus::Completed,
            'movement_id' => $movement->id,
            'completed_at' => now(),
        ]);
        $audit = app(RecordProjectAudit::class)->handle(
            $project,
            $owner,
            'planned_movement',
            $plan->id,
            'completed',
            $before,
            $plan->fresh()->auditSnapshot(),
        );

        $this->assertTrue($plan->fresh()->movement->is($movement));
        $this->assertTrue($movement->fresh()->plannedMovement->is($plan));
        $this->assertSame('Planificación', $audit->subjectLabel());
        $this->assertSame('Registro como realizado', $audit->actionLabel());
        $this->assertSame('registró como realizado una planificación', $audit->activityDescription());
    }

    public function test_active_members_manage_plans_but_outsiders_and_archived_projects_do_not(): void
    {
        [$project, $owner, $member] = $this->financialProject();
        $outsider = User::factory()->create();

        $this->assertTrue(Gate::forUser($owner)->allows('managePlannedMovements', $project));
        $this->assertTrue(Gate::forUser($member)->allows('managePlannedMovements', $project));
        $this->assertFalse(Gate::forUser($outsider)->allows('managePlannedMovements', $project));

        $project->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);

        $this->assertFalse(Gate::forUser($owner)->allows('managePlannedMovements', $project));
        $this->assertFalse(Gate::forUser($member)->allows('managePlannedMovements', $project));
        $this->assertTrue(Gate::forUser($member)->allows('view', $project));
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
            'name' => 'Principal',
            'type' => FinancialAccountType::Checking,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-01-01',
            'position' => 10,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
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

        return [$project, $owner, $member, $account, $category];
    }

    /** @param array<string, mixed> $overrides */
    private function movement(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
        array $overrides = [],
    ): Movement {
        return Movement::create(array_merge([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 1250,
            'occurred_on' => '2026-09-15',
            'concept' => 'Compra',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ], $overrides));
    }
}
