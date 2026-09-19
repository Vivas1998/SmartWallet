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
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceManagementTest extends TestCase
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

    public function test_monthly_recurrences_keep_the_original_day_and_are_idempotent(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $template = $this->expenseTemplate($project, $owner, $account, $category, [
            'start_on' => '2026-01-31', 'anchor_day' => 31, 'next_occurrence_on' => '2026-01-31',
        ]);

        $generator = app(GenerateDueRecurrences::class);
        $first = $generator->handle(CarbonImmutable::parse('2026-03-31'));
        $second = $generator->handle(CarbonImmutable::parse('2026-03-31'));

        $this->assertSame(3, $first['generated']);
        $this->assertSame(0, $second['generated']);
        $this->assertEquals(
            ['2026-01-31', '2026-02-28', '2026-03-31'],
            $project->movements()->orderBy('occurred_on')->pluck('occurred_on')->map->format('Y-m-d')->all(),
        );
        $this->assertSame(3, $template->occurrences()->count());
        $this->assertSame(3, $project->movements()->distinct('recurrence_occurrence_id')->count('recurrence_occurrence_id'));
        $this->assertDatabaseHas('account_entries', ['financial_account_id' => $account->id, 'signed_amount_cents' => -1250]);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'action' => 'generated', 'actor_user_id' => null]);
    }

    public function test_recovery_mode_records_a_visible_summary(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $this->expenseTemplate($project, $owner, $account, $category, [
            'start_on' => '2026-09-13', 'anchor_day' => 13, 'next_occurrence_on' => '2026-09-13',
            'frequency' => RecurrenceFrequency::Daily,
        ]);

        $this->artisan('smartwallet:generate-recurrences', ['--recovery' => true])->assertSuccessful();

        $this->assertSame(3, $project->movements()->count());
        $this->assertDatabaseHas('recurrence_recovery_notices', ['project_id' => $project->id, 'generated_count' => 3, 'dismissed_at' => null]);
        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertSee('recuperó 3 movimiento(s)');
    }

    public function test_only_an_owner_can_manage_series_but_a_member_can_edit_one_generated_occurrence(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);

        $this->actingAs($member)->post(route('recurrences.store', $project), $this->recurrenceData($account, $category))
            ->assertForbidden();

        $this->actingAs($owner)->post(route('recurrences.store', $project), $this->recurrenceData($account, $category))
            ->assertRedirect(route('recurrences.index', $project));
        $movement = $project->movements()->firstOrFail();
        $this->assertNotNull($movement->generated_automatically_at);

        $this->actingAs($member)->patch(route('movements.update', [$project, $movement]), [
            'type' => 'expense', 'amount' => '18', 'occurred_on' => '2026-09-15', 'concept' => 'Solo esta aparición',
            'category_id' => $category->id, 'financial_account_id' => $account->id, 'paid_by_user_id' => $member->id,
        ])->assertRedirect();

        $this->assertSame(1800, $movement->fresh()->amount_cents);
        $this->assertSame(1250, $project->recurrenceTemplates()->firstOrFail()->amount_cents);
    }

    public function test_pause_resume_and_skip_have_explicit_schedule_semantics(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $template = $this->expenseTemplate($project, $owner, $account, $category, [
            'start_on' => '2026-07-15', 'anchor_day' => 15, 'next_occurrence_on' => '2026-07-15',
        ]);

        $this->actingAs($owner)->post(route('recurrences.pause', [$project, $template]))->assertRedirect();
        $this->actingAs($owner)->post(route('recurrences.resume', [$project, $template]))->assertRedirect();

        $this->assertDatabaseHas('recurrence_occurrences', ['recurrence_template_id' => $template->id, 'scheduled_on' => '2026-07-15', 'status' => 'skipped']);
        $this->assertDatabaseHas('recurrence_occurrences', ['recurrence_template_id' => $template->id, 'scheduled_on' => '2026-08-15', 'status' => 'skipped']);
        $this->assertDatabaseHas('movements', ['recurrence_template_id' => $template->id, 'occurred_on' => '2026-09-15']);

        $this->actingAs($owner)->post(route('recurrences.skip', [$project, $template->fresh()]))->assertRedirect();
        $this->assertDatabaseHas('recurrence_occurrences', ['recurrence_template_id' => $template->id, 'scheduled_on' => '2026-10-15', 'status' => 'skipped']);
        $this->assertSame('2026-11-15', $template->fresh()->next_occurrence_on->format('Y-m-d'));
    }

    public function test_a_recurring_transfer_can_contribute_to_a_savings_goal(): void
    {
        [$project, $owner, $source] = $this->financialProject();
        $savings = $this->account($project, $owner, FinancialAccountType::Savings, 'Ahorro');
        $goal = SavingsGoal::create([
            'project_id' => $project->id, 'financial_account_id' => $savings->id, 'name' => 'Colchón',
            'target_amount_cents' => 100000, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
        $template = RecurrenceTemplate::create([
            'project_id' => $project->id, 'type' => MovementType::Transfer, 'amount_cents' => 10000,
            'concept' => 'Ahorro mensual', 'financial_account_id' => $source->id, 'destination_account_id' => $savings->id,
            'savings_goal_id' => $goal->id, 'frequency' => RecurrenceFrequency::Monthly, 'start_on' => '2026-09-15',
            'anchor_day' => 15, 'next_occurrence_on' => '2026-09-15', 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);

        app(GenerateDueRecurrences::class)->handle();

        $this->assertSame(10000, $goal->fresh()->currentAmountCents());
        $this->assertDatabaseHas('goal_allocations', ['savings_goal_id' => $goal->id, 'direction' => 'contribution']);

        $this->actingAs($owner)->post(route('savings-goals.archive', [$project, $goal]))->assertRedirect();
        $this->assertNotNull($template->fresh()->paused_at);
    }

    /** @return array{Project, User, FinancialAccount, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        $this->membership($project, $owner, $owner, ProjectRole::Owner);
        $account = $this->account($project, $owner, FinancialAccountType::Checking, 'Principal');
        $category = Category::create([
            'project_id' => $project->id, 'type' => CategoryType::Expense, 'name' => 'Casa', 'color' => '#147d68',
            'icon' => 'home', 'position' => 10, 'is_initial' => false, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);

        return [$project, $owner, $account, $category];
    }

    private function expenseTemplate(Project $project, User $owner, FinancialAccount $account, Category $category, array $overrides = []): RecurrenceTemplate
    {
        return RecurrenceTemplate::create(array_merge([
            'project_id' => $project->id, 'type' => MovementType::Expense, 'amount_cents' => 1250, 'concept' => 'Cuota',
            'category_id' => $category->id, 'financial_account_id' => $account->id, 'paid_by_user_id' => $owner->id,
            'frequency' => RecurrenceFrequency::Monthly, 'start_on' => '2026-09-15', 'anchor_day' => 15,
            'next_occurrence_on' => '2026-09-15', 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ], $overrides));
    }

    private function recurrenceData(FinancialAccount $account, Category $category): array
    {
        return [
            'movement_kind' => 'expense', 'amount' => '12,50', 'concept' => 'Cuota', 'category_id' => $category->id,
            'financial_account_id' => $account->id, 'paid_by_user_id' => $account->project->creator_user_id,
            'frequency' => 'monthly', 'start_on' => '2026-09-15', 'next_occurrence_on' => '2026-09-15',
        ];
    }

    private function account(Project $project, User $owner, FinancialAccountType $type, string $name): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id, 'name' => $name, 'type' => $type, 'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-01-01', 'position' => 10, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
    }

    private function membership(Project $project, User $user, User $actor, ProjectRole $role): void
    {
        ProjectMember::create([
            'project_id' => $project->id, 'user_id' => $user->id, 'role' => $role,
            'added_by_user_id' => $actor->id, 'joined_at' => now(),
        ]);
    }
}
