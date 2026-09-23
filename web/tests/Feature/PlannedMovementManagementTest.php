<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\BuildMonthlyCalendar;
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
use App\Models\SavingsGoal;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannedMovementManagementTest extends TestCase
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

    public function test_an_active_member_can_create_edit_and_cancel_a_plan_without_accounting_it(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $tag = Tag::create([
            'project_id' => $project->id,
            'name' => 'Vencimiento',
            'created_by_user_id' => $member->id,
            'updated_by_user_id' => $member->id,
        ]);

        $this->actingAs($member)->get(route('planned-movements.index', $project))
            ->assertOk()
            ->assertSee('Planificaciones puntuales');
        $this->actingAs($member)->get(route('planned-movements.create', $project))->assertOk();
        $this->actingAs($member)->post(route('planned-movements.store', $project), [
            ...$this->expenseData($account, $category),
            'tag_ids' => [$tag->id],
        ])->assertRedirect(route('planned-movements.index', $project));

        $plan = $project->plannedMovements()->firstOrFail();
        $this->assertSame(PlannedMovementStatus::Pending, $plan->status);
        $this->assertSame([$tag->id], $plan->tags()->pluck('tags.id')->all());
        $this->assertSame(0, $project->movements()->count());
        $this->assertDatabaseCount('account_entries', 0);
        $this->assertDatabaseHas('audit_logs', [
            'project_id' => $project->id,
            'subject_type' => 'planned_movement',
            'subject_id' => $plan->id,
            'action' => 'created',
            'actor_user_id' => $member->id,
        ]);

        $this->actingAs($member)->put(route('planned-movements.update', [$project, $plan]), [
            ...$this->expenseData($account, $category),
            'amount' => '72,50',
            'concept' => 'Seguro actualizado',
            'due_on' => '2026-10-20',
            'tag_ids' => [$tag->id],
        ])->assertRedirect(route('planned-movements.index', $project));

        $this->assertSame(7250, $plan->fresh()->amount_cents);
        $this->assertSame('2026-10-20', $plan->fresh()->due_on->toDateString());
        $this->assertSame(0, $project->movements()->count());

        $this->actingAs($member)->post(route('planned-movements.cancel', [$project, $plan]))->assertRedirect();
        $plan->refresh();
        $this->assertSame(PlannedMovementStatus::Cancelled, $plan->status);
        $this->assertSame($member->id, $plan->cancelled_by_user_id);
        $this->assertNotNull($plan->cancelled_at);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'planned_movement',
            'subject_id' => $plan->id,
            'action' => 'cancelled',
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('planned-movements.index', $project))->assertForbidden();
        $project->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);
        $this->actingAs($member)->get(route('planned-movements.index', $project))->assertOk();
        $this->actingAs($member)->get(route('planned-movements.create', $project))->assertForbidden();
    }

    public function test_completing_a_plan_creates_one_real_movement_and_keeps_the_due_date_in_the_calendar(): void
    {
        [$project, , $member, $account, $category] = $this->financialProject();
        $tag = Tag::create([
            'project_id' => $project->id,
            'name' => 'Casa',
            'created_by_user_id' => $member->id,
            'updated_by_user_id' => $member->id,
        ]);
        $plan = $this->plan($project, $member, $account, $category, [
            'amount_cents' => 5000,
            'due_on' => '2026-09-15',
            'concept' => 'Seguro previsto',
        ]);
        $plan->tags()->attach($tag);

        $this->actingAs($member)->get(route('planned-movements.complete', [$project, $plan]))
            ->assertOk()
            ->assertSee('Registrar como realizado')
            ->assertSee('50,00 €');
        $this->actingAs($member)->post(route('planned-movements.complete.store', [$project, $plan]), [
            'amount' => '55,00',
            'occurred_on' => '2026-09-14',
            'concept' => 'Seguro real',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $member->id,
            'tag_ids' => [$tag->id],
        ])->assertRedirect(route('planned-movements.index', $project));

        $plan->refresh();
        $movement = $plan->movement;
        $this->assertSame(PlannedMovementStatus::Completed, $plan->status);
        $this->assertNotNull($plan->completed_at);
        $this->assertNotNull($movement);
        $this->assertSame(5500, $movement->amount_cents);
        $this->assertSame('2026-09-14', $movement->occurred_on->toDateString());
        $this->assertFalse($movement->show_in_calendar);
        $this->assertSame([$tag->id], $movement->tags()->pluck('tags.id')->all());
        $this->assertDatabaseHas('account_entries', [
            'movement_id' => $movement->id,
            'financial_account_id' => $account->id,
            'signed_amount_cents' => -5500,
        ]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'movement', 'subject_id' => $movement->id, 'action' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'planned_movement', 'subject_id' => $plan->id, 'action' => 'completed']);
        $this->actingAs($member)->get(route('movements.edit', [$project, $movement]))
            ->assertOk()
            ->assertSee('Visible automáticamente en el calendario');

        $events = collect(app(BuildMonthlyCalendar::class)->handle(
            $project,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-15'),
        )['events']);
        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('planned_movement:'.$plan->id, $event['id']);
        $this->assertSame('2026-09-15', $event['scheduled_on']);
        $this->assertSame('2026-09-14', $event['effective_on']);
        $this->assertSame('early', $event['punctuality']);
        $this->assertSame(5000, $event['planned_amount_cents']);
        $this->assertSame(5500, $event['actual_amount_cents']);

        $this->actingAs($member)->post(route('planned-movements.complete.store', [$project, $plan]), [
            'amount' => '55,00',
        ])->assertSessionHasErrors('planned_movement');
        $this->assertSame(1, $project->movements()->count());
    }

    public function test_completion_warns_about_a_possible_duplicate_and_can_be_confirmed(): void
    {
        [$project, , $member, $account, $category] = $this->financialProject();
        Movement::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 3000,
            'occurred_on' => '2026-09-15',
            'concept' => 'Movimiento existente',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $member->id,
            'created_by_user_id' => $member->id,
            'updated_by_user_id' => $member->id,
        ]);
        $plan = $this->plan($project, $member, $account, $category, ['amount_cents' => 3000]);
        $data = [
            'amount' => '30,00',
            'occurred_on' => '2026-09-15',
            'concept' => 'Plan realizado',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $member->id,
        ];

        $this->actingAs($member)->post(route('planned-movements.complete.store', [$project, $plan]), $data)
            ->assertRedirect()
            ->assertSessionHas('possible_duplicate');
        $this->assertSame(PlannedMovementStatus::Pending, $plan->fresh()->status);
        $this->assertSame(1, $project->movements()->count());

        $this->actingAs($member)->post(route('planned-movements.complete.store', [$project, $plan]), [
            ...$data,
            'allow_duplicate' => '1',
        ])->assertRedirect(route('planned-movements.index', $project));
        $this->assertSame(PlannedMovementStatus::Completed, $plan->fresh()->status);
        $this->assertSame(2, $project->movements()->count());
    }

    public function test_a_planned_transfer_can_become_a_goal_contribution(): void
    {
        [$project, $owner, $member, $source] = $this->financialProject();
        $savings = $this->account($project, $owner, FinancialAccountType::Savings, 'Ahorro');
        $goal = SavingsGoal::create([
            'project_id' => $project->id,
            'financial_account_id' => $savings->id,
            'name' => 'Colchón',
            'target_amount_cents' => 100000,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $plan = PlannedMovement::create([
            'project_id' => $project->id,
            'type' => MovementType::Transfer,
            'amount_cents' => 10000,
            'due_on' => '2026-09-15',
            'concept' => 'Ahorro previsto',
            'financial_account_id' => $source->id,
            'destination_account_id' => $savings->id,
            'savings_goal_id' => $goal->id,
            'status' => PlannedMovementStatus::Pending,
            'created_by_user_id' => $member->id,
            'updated_by_user_id' => $member->id,
        ]);

        $this->actingAs($member)->post(route('planned-movements.complete.store', [$project, $plan]), [
            'amount' => '100,00',
            'occurred_on' => '2026-09-15',
            'concept' => 'Ahorro realizado',
            'financial_account_id' => $source->id,
            'destination_account_id' => $savings->id,
            'savings_goal_id' => $goal->id,
        ])->assertRedirect(route('planned-movements.index', $project));

        $movement = $plan->fresh()->movement;
        $this->assertSame(MovementType::Transfer, $movement->type);
        $this->assertCount(2, $movement->entries);
        $this->assertDatabaseHas('account_entries', ['movement_id' => $movement->id, 'financial_account_id' => $source->id, 'signed_amount_cents' => -10000]);
        $this->assertDatabaseHas('account_entries', ['movement_id' => $movement->id, 'financial_account_id' => $savings->id, 'signed_amount_cents' => 10000]);
        $this->assertDatabaseHas('goal_allocations', ['movement_id' => $movement->id, 'savings_goal_id' => $goal->id, 'direction' => 'contribution']);
        $this->assertSame(10000, $goal->currentAmountCents());
    }

    public function test_foreign_financial_relations_are_rejected(): void
    {
        [$project, , $member, $account, $category] = $this->financialProject();
        [$otherProject, $otherOwner, , $otherAccount, $otherCategory] = $this->financialProject();

        $this->actingAs($member)->post(route('planned-movements.store', $project), [
            ...$this->expenseData($account, $category),
            'financial_account_id' => $otherAccount->id,
            'category_id' => $otherCategory->id,
        ])->assertSessionHasErrors('financial_account_id');
        $this->assertSame(0, $project->plannedMovements()->count());
        $this->assertSame(0, $otherProject->plannedMovements()->count());
        $this->assertNotNull($otherOwner);
    }

    /** @return array{Project, User, User, FinancialAccount, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        $this->membership($project, $owner, $owner, ProjectRole::Owner);
        $this->membership($project, $member, $owner, ProjectRole::Member);
        $account = $this->account($project, $owner, FinancialAccountType::Checking, 'Principal');
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

    /** @return array<string, mixed> */
    private function expenseData(FinancialAccount $account, Category $category): array
    {
        return [
            'movement_kind' => 'expense',
            'amount' => '50,00',
            'due_on' => '2026-10-15',
            'concept' => 'Seguro previsto',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $account->project->creator_user_id,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function plan(
        Project $project,
        User $member,
        FinancialAccount $account,
        Category $category,
        array $overrides = [],
    ): PlannedMovement {
        return PlannedMovement::create(array_merge([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 3000,
            'due_on' => '2026-09-15',
            'concept' => 'Planificación',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $member->id,
            'status' => PlannedMovementStatus::Pending,
            'created_by_user_id' => $member->id,
            'updated_by_user_id' => $member->id,
        ], $overrides));
    }

    private function account(Project $project, User $owner, FinancialAccountType $type, string $name): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id,
            'name' => $name,
            'type' => $type,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-01-01',
            'position' => 10,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
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
}
