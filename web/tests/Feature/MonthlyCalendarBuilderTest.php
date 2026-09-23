<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\BuildMonthlyCalendar;
use App\Actions\Recurrences\GenerateDueRecurrences;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\PlannedMovementStatus;
use App\Enums\ProjectRole;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceOccurrenceStatus;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RecurrenceOccurrence;
use App\Models\RecurrenceTemplate;
use App\Models\User;
use App\Services\Recurrences\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyCalendarBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shared_schedule_preserves_monthly_anchors_annual_leap_days_and_end_dates(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $schedule = app(RecurrenceSchedule::class);
        $monthly = $this->recurrence($project, $owner, $account, $category, [
            'start_on' => '2026-01-31',
            'anchor_day' => 31,
            'next_occurrence_on' => '2026-01-31',
            'ends_on' => '2026-04-30',
        ]);

        $this->assertSame(
            ['2026-02-28', '2026-03-31', '2026-04-30'],
            array_map(
                fn (CarbonImmutable $date): string => $date->toDateString(),
                $schedule->datesBetween(
                    $monthly,
                    CarbonImmutable::parse('2026-02-01'),
                    CarbonImmutable::parse('2026-05-31'),
                ),
            ),
        );

        $annual = $this->recurrence($project, $owner, $account, $category, [
            'frequency' => RecurrenceFrequency::Annual,
            'start_on' => '2024-02-29',
            'anchor_day' => 29,
            'next_occurrence_on' => '2024-02-29',
            'ends_on' => '2028-12-31',
        ]);

        $this->assertSame(
            ['2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29'],
            array_map(
                fn (CarbonImmutable $date): string => $date->toDateString(),
                $schedule->datesBetween(
                    $annual,
                    CarbonImmutable::parse('2025-01-01'),
                    CarbonImmutable::parse('2028-12-31'),
                ),
            ),
        );
    }

    public function test_monthly_reading_combines_only_visible_sources_without_duplicate_linked_movements(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $this->movement($project, $owner, $account, $category, '2026-09-02', false, 'Movimiento oculto');
        $visible = $this->movement($project, $owner, $account, $category, '2026-09-03', true, 'Movimiento visible');

        $completedMovement = $this->movement($project, $owner, $account, $category, '2026-09-10', true, 'Importe real', 5500);
        $completedPlan = $this->plannedMovement($project, $owner, $account, $category, [
            'amount_cents' => 5000,
            'due_on' => '2026-09-12',
            'concept' => 'Seguro planificado',
            'status' => PlannedMovementStatus::Completed,
            'movement_id' => $completedMovement->id,
            'completed_at' => now(),
        ]);
        $overduePlan = $this->plannedMovement($project, $owner, $account, $category, [
            'due_on' => '2026-09-09',
            'concept' => 'Plan vencido',
        ]);
        $cancelledPlan = $this->plannedMovement($project, $owner, $account, $category, [
            'due_on' => '2026-09-13',
            'concept' => 'Plan cancelado',
            'status' => PlannedMovementStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $owner->id,
        ]);

        $recurrence = $this->recurrence($project, $owner, $account, $category, [
            'frequency' => RecurrenceFrequency::Weekly,
            'start_on' => '2026-09-01',
            'anchor_day' => 1,
            'next_occurrence_on' => '2026-09-01',
        ]);
        app(GenerateDueRecurrences::class)->handle(CarbonImmutable::parse('2026-09-08'));
        $recurrence->refresh();
        $recurrence->movements()->first()?->update(['show_in_calendar' => true]);
        RecurrenceOccurrence::create([
            'recurrence_template_id' => $recurrence->id,
            'scheduled_on' => '2026-09-15',
            'status' => RecurrenceOccurrenceStatus::Skipped,
            'processed_at' => now(),
        ]);
        $recurrence->update(['next_occurrence_on' => '2026-09-22']);

        $this->recurrence($project, $owner, $account, $category, [
            'concept' => 'Serie pausada',
            'start_on' => '2026-09-20',
            'anchor_day' => 20,
            'next_occurrence_on' => '2026-09-20',
            'paused_at' => now(),
        ]);

        [$otherProject, $otherOwner, $otherAccount, $otherCategory] = $this->financialProject();
        $this->movement($otherProject, $otherOwner, $otherAccount, $otherCategory, '2026-09-04', true, 'Proyecto ajeno');

        $calendar = app(BuildMonthlyCalendar::class)->handle(
            $project,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-10'),
        );
        $events = collect($calendar['events']);

        $this->assertSame('2026-09', $calendar['month']);
        $this->assertSame('2026-09-01', $calendar['starts_on']);
        $this->assertSame('2026-09-30', $calendar['ends_on']);
        $this->assertCount(9, $events);
        $this->assertCount(9, $events->pluck('id')->unique());
        $this->assertTrue($events->contains('id', 'movement:'.$visible->id));
        $this->assertFalse($events->contains('movement_id', $completedMovement->id) && $events->where('movement_id', $completedMovement->id)->count() > 1);
        $this->assertFalse($events->contains('concept', 'Movimiento oculto'));
        $this->assertFalse($events->contains('concept', 'Serie pausada'));
        $this->assertFalse($events->contains('concept', 'Proyecto ajeno'));

        $completedEvent = $events->firstWhere('id', 'planned_movement:'.$completedPlan->id);
        $this->assertSame('2026-09-12', $completedEvent['scheduled_on']);
        $this->assertSame('2026-09-10', $completedEvent['effective_on']);
        $this->assertSame('early', $completedEvent['punctuality']);
        $this->assertSame(5000, $completedEvent['planned_amount_cents']);
        $this->assertSame(5500, $completedEvent['actual_amount_cents']);
        $this->assertFalse($completedEvent['is_estimated']);
        $this->assertSame('overdue', $events->firstWhere('id', 'planned_movement:'.$overduePlan->id)['status']);
        $this->assertSame('cancelled', $events->firstWhere('id', 'planned_movement:'.$cancelledPlan->id)['status']);

        $recurrenceEvents = $events->where('recurrence_template_id', $recurrence->id);
        $this->assertSame(
            ['2026-09-01', '2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'],
            $recurrenceEvents->pluck('scheduled_on')->values()->all(),
        );
        $this->assertSame(
            ['completed', 'completed', 'skipped', 'planned', 'planned'],
            $recurrenceEvents->pluck('status')->values()->all(),
        );
    }

    /** @return array{Project, User, FinancialAccount, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);
        $account = FinancialAccount::create([
            'project_id' => $project->id,
            'name' => 'Principal',
            'type' => FinancialAccountType::Checking,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2024-01-01',
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

        return [$project, $owner, $account, $category];
    }

    private function movement(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
        string $date,
        bool $visible,
        string $concept,
        int $amountCents = 1250,
    ): Movement {
        return Movement::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => $amountCents,
            'occurred_on' => $date,
            'concept' => $concept,
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'show_in_calendar' => $visible,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function plannedMovement(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
        array $overrides = [],
    ): PlannedMovement {
        return PlannedMovement::create(array_merge([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 2500,
            'due_on' => '2026-09-20',
            'concept' => 'Planificación',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'status' => PlannedMovementStatus::Pending,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function recurrence(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
        array $overrides = [],
    ): RecurrenceTemplate {
        return RecurrenceTemplate::create(array_merge([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 1250,
            'concept' => 'Cuota recurrente',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'start_on' => '2026-09-15',
            'anchor_day' => 15,
            'next_occurrence_on' => '2026-09-15',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ], $overrides));
    }
}
