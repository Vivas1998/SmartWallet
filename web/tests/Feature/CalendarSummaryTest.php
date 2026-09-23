<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\BuildCalendarSummary;
use App\Actions\Calendar\BuildMonthlyCalendar;
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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_real_accounting_from_calendar_forecasts(): void
    {
        [$project, $owner, $account, $destination, $expenseCategory, $incomeCategory] = $this->financialProject();
        $project->monthlyBudgets()->create([
            'month' => '2026-09-01',
            'total_limit_cents' => 100000,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $visibleExpense = $this->movement($project, $owner, $account, $expenseCategory, MovementType::Expense, 20000, true, 'Gasto visible');
        $this->movement($project, $owner, $account, $expenseCategory, MovementType::Expense, 10000, false, 'Gasto oculto');
        $this->movement($project, $owner, $account, $expenseCategory, MovementType::Refund, 5000, true, 'Devolución visible', $visibleExpense);
        $this->movement($project, $owner, $account, $incomeCategory, MovementType::Income, 50000, false, 'Ingreso real');

        $this->plan($project, $owner, $account, $expenseCategory, MovementType::Expense, 7000, '2026-09-20', 'Gasto pendiente');
        $this->plan($project, $owner, $account, $incomeCategory, MovementType::Income, 9000, '2026-09-22', 'Ingreso previsto');
        $this->plan($project, $owner, $account, $expenseCategory, MovementType::Transfer, 12000, '2026-09-23', 'Transferencia prevista', [
            'category_id' => null,
            'destination_account_id' => $destination->id,
            'paid_by_user_id' => null,
        ]);
        $this->plan($project, $owner, $account, $expenseCategory, MovementType::Expense, 11000, '2026-09-10', 'Gasto cancelado', [
            'status' => PlannedMovementStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $owner->id,
        ]);
        RecurrenceTemplate::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 3000,
            'concept' => 'Cuota futura',
            'category_id' => $expenseCategory->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'start_on' => '2026-09-25',
            'anchor_day' => 25,
            'next_occurrence_on' => '2026-09-25',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $month = CarbonImmutable::parse('2026-09-01', 'Europe/Madrid');
        $events = app(BuildMonthlyCalendar::class)->handle(
            $project,
            $month,
            CarbonImmutable::parse('2026-09-15', 'Europe/Madrid'),
        )['events'];
        $summary = app(BuildCalendarSummary::class)->handle($project, $month, $events);

        $this->assertSame(100000, $summary['budget_cents']);
        $this->assertSame(25000, $summary['actual_expense_cents']);
        $this->assertSame(15000, $summary['calendar_expense_cents']);
        $this->assertSame(10000, $summary['pending_expense_cents']);
        $this->assertSame(35000, $summary['forecast_expense_cents']);
        $this->assertSame(9000, $summary['pending_income_cents']);
        $this->assertSame(75000, $summary['actual_remaining_cents']);
        $this->assertSame(65000, $summary['forecast_remaining_cents']);
        $this->assertSame(2, $summary['calendar_expense_count']);
        $this->assertSame(2, $summary['pending_expense_count']);
        $this->assertSame(1, $summary['pending_income_count']);
    }

    /** @return array{Project, User, FinancialAccount, FinancialAccount, Category, Category} */
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
        $account = $this->account($project, $owner, 'Principal', 10);
        $destination = $this->account($project, $owner, 'Ahorro', 20);
        $expenseCategory = $this->category($project, $owner, CategoryType::Expense, 'Casa');
        $incomeCategory = $this->category($project, $owner, CategoryType::Income, 'Nómina');

        return [$project, $owner, $account, $destination, $expenseCategory, $incomeCategory];
    }

    private function account(Project $project, User $owner, string $name, int $position): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id,
            'name' => $name,
            'type' => FinancialAccountType::Checking,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2024-01-01',
            'position' => $position,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    private function category(Project $project, User $owner, CategoryType $type, string $name): Category
    {
        return Category::create([
            'project_id' => $project->id,
            'type' => $type,
            'name' => $name,
            'color' => '#147d68',
            'icon' => 'home',
            'position' => 10,
            'is_initial' => false,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    private function movement(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
        MovementType $type,
        int $amountCents,
        bool $visible,
        string $concept,
        ?Movement $original = null,
    ): Movement {
        return Movement::create([
            'project_id' => $project->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'occurred_on' => '2026-09-12',
            'concept' => $concept,
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'original_movement_id' => $original?->id,
            'show_in_calendar' => $visible,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function plan(
        Project $project,
        User $owner,
        FinancialAccount $account,
        Category $category,
        MovementType $type,
        int $amountCents,
        string $date,
        string $concept,
        array $overrides = [],
    ): PlannedMovement {
        return PlannedMovement::create(array_merge([
            'project_id' => $project->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'due_on' => $date,
            'concept' => $concept,
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'status' => PlannedMovementStatus::Pending,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ], $overrides));
    }
}
