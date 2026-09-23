<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\BuildMonthlyCalendar;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_record_an_expense_with_an_exact_account_entry(): void
    {
        [$project, $owner, $account, $category, $subcategory] = $this->financialProject();
        $member = User::factory()->create();
        $this->membership($project, $member, $owner, ProjectRole::Member);

        $this->actingAs($member)->post(route('movements.store', $project), $this->movementData($account, $category, $subcategory, [
            'amount' => '19,95', 'paid_by_user_id' => $owner->id,
        ]))->assertRedirect(route('movements.index', ['project' => $project, 'month' => '2026-09']));

        $this->assertDatabaseHas('movements', [
            'project_id' => $project->id, 'amount_cents' => 1995, 'created_by_user_id' => $member->id,
            'paid_by_user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('account_entries', [
            'project_id' => $project->id, 'financial_account_id' => $account->id, 'signed_amount_cents' => -1995,
        ]);
    }

    public function test_an_income_adds_to_a_normal_account_and_cannot_target_a_credit_card(): void
    {
        [$project, $owner, $account] = $this->financialProject();
        $income = $this->category($project, $owner, 'Nómina', CategoryType::Income);
        $credit = $this->account($project, $owner, FinancialAccountType::CreditCard, 'Tarjeta');

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $income, null, [
            'type' => 'income', 'amount' => '2.000', 'concept' => 'Nómina',
        ]))->assertRedirect();
        $this->assertDatabaseHas('account_entries', ['financial_account_id' => $account->id, 'signed_amount_cents' => 200000]);

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($credit, $income, null, [
            'type' => 'income', 'amount' => '200', 'concept' => 'Ingreso inválido',
        ]))->assertSessionHasErrors('financial_account_id');
    }

    public function test_credit_card_expenses_increase_the_recorded_debt(): void
    {
        [$project, $owner, , $category] = $this->financialProject();
        $credit = $this->account($project, $owner, FinancialAccountType::CreditCard, 'Tarjeta');

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($credit, $category, null, [
            'amount' => '50',
        ]))->assertRedirect();

        $this->assertDatabaseHas('account_entries', ['financial_account_id' => $credit->id, 'signed_amount_cents' => 5000]);
    }

    public function test_manual_movements_are_hidden_by_default_and_can_be_added_to_or_removed_from_the_calendar(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();

        $this->actingAs($owner)->get(route('movements.create', $project))
            ->assertOk()
            ->assertSee('Mostrar en el calendario');
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, [
            'concept' => 'Movimiento oculto',
        ]))->assertRedirect();
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, [
            'amount' => '25,00',
            'concept' => 'Movimiento visible',
            'show_in_calendar' => '1',
        ]))->assertRedirect();

        $hidden = $project->movements()->where('concept', 'Movimiento oculto')->firstOrFail();
        $visible = $project->movements()->where('concept', 'Movimiento visible')->firstOrFail();
        $this->assertFalse($hidden->show_in_calendar);
        $this->assertTrue($visible->show_in_calendar);

        $events = collect(app(BuildMonthlyCalendar::class)->handle(
            $project,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-15'),
        )['events']);
        $this->assertFalse($events->contains('movement_id', $hidden->id));
        $this->assertTrue($events->contains('movement_id', $visible->id));

        $this->actingAs($owner)->patch(route('movements.update', [$project, $visible]), $this->movementData($account, $category, null, [
            'amount' => '25,00',
            'concept' => 'Movimiento visible',
            'show_in_calendar' => '0',
        ]))->assertRedirect();
        $this->assertFalse($visible->fresh()->show_in_calendar);
        $this->assertFalse($project->auditLogs()->latest('id')->firstOrFail()->after_values['show_in_calendar']);
    }

    public function test_a_possible_manual_duplicate_warns_but_can_be_saved_anyway(): void
    {
        [$project, $owner, $account, $category, $subcategory] = $this->financialProject();
        $first = $this->movementData($account, $category, $subcategory);
        $second = $this->movementData($account, $category, $subcategory, ['concept' => 'Segunda compra igual']);

        $this->actingAs($owner)->post(route('movements.store', $project), $first)->assertRedirect();
        $this->actingAs($owner)->post(route('movements.store', $project), $second)
            ->assertRedirect()
            ->assertSessionHas('possible_duplicate');
        $this->assertSame(1, $project->movements()->count());

        $this->actingAs($owner)->post(route('movements.store', $project), [...$second, 'allow_duplicate' => '1'])->assertRedirect();
        $this->assertSame(2, $project->movements()->count());
    }

    public function test_foreign_categories_accounts_and_members_are_rejected(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        [$otherProject, $otherOwner, $otherAccount, $otherCategory] = $this->financialProject();

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($otherAccount, $category))
            ->assertSessionHasErrors('financial_account_id');
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $otherCategory))
            ->assertSessionHasErrors('category_id');
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, ['paid_by_user_id' => $otherOwner->id]))
            ->assertSessionHasErrors('paid_by_user_id');
        $this->assertSame(0, $project->movements()->count());
    }

    public function test_a_subcategory_must_belong_to_the_selected_main_category(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $otherCategory = $this->category($project, $owner, 'Ocio');
        $wrongChild = $this->category($project, $owner, 'Cine', CategoryType::Expense, $otherCategory->id);

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, $wrongChild))
            ->assertSessionHasErrors('subcategory_id');
    }

    public function test_future_movements_and_dates_before_the_initial_balance_are_rejected(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, ['occurred_on' => '2099-01-01']))
            ->assertSessionHasErrors('occurred_on');
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, ['occurred_on' => '2025-12-31']))
            ->assertSessionHasErrors('occurred_on');
    }

    public function test_non_members_cannot_view_or_create_project_movements(): void
    {
        [$project, , $account, $category] = $this->financialProject();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('movements.index', $project))->assertForbidden();
        $this->actingAs($outsider)->post(route('movements.store', $project), $this->movementData($account, $category))->assertForbidden();
    }

    public function test_a_transfer_updates_two_accounts_without_creating_income_or_expense(): void
    {
        [$project, $owner, $source] = $this->financialProject();
        $destination = $this->account($project, $owner, FinancialAccountType::Savings, 'Ahorro');

        $this->actingAs($owner)->post(route('movements.transfer.store', $project), [
            'amount' => '125,50',
            'occurred_on' => '2026-09-15',
            'concept' => 'Ahorro mensual',
            'financial_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertRedirect();

        $movement = $project->movements()->firstOrFail();
        $this->assertSame('transfer', $movement->type->value);
        $this->assertDatabaseHas('account_entries', ['movement_id' => $movement->id, 'financial_account_id' => $source->id, 'signed_amount_cents' => -12550]);
        $this->assertDatabaseHas('account_entries', ['movement_id' => $movement->id, 'financial_account_id' => $destination->id, 'signed_amount_cents' => 12550]);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_id' => $movement->id, 'action' => 'created']);
    }

    public function test_card_payments_reduce_debt_and_investments_are_recorded_as_capital_contributions(): void
    {
        [$project, $owner, $source] = $this->financialProject();
        $card = $this->account($project, $owner, FinancialAccountType::CreditCard, 'Tarjeta');
        $investment = $this->account($project, $owner, FinancialAccountType::ExternalInvestment, 'Cartera externa');

        $this->actingAs($owner)->post(route('movements.transfer.store', $project), [
            'amount' => '80', 'occurred_on' => '2026-09-15', 'concept' => 'Pago tarjeta',
            'financial_account_id' => $source->id, 'destination_account_id' => $card->id,
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('movements.transfer.store', $project), [
            'amount' => '100', 'occurred_on' => '2026-09-15', 'concept' => 'Aportación mensual',
            'financial_account_id' => $source->id, 'destination_account_id' => $investment->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('account_entries', ['financial_account_id' => $card->id, 'signed_amount_cents' => -8000]);
        $this->assertDatabaseHas('account_entries', ['financial_account_id' => $investment->id, 'signed_amount_cents' => 10000]);
        $this->assertDatabaseHas('movements', ['destination_account_id' => $investment->id, 'type' => 'investment_contribution']);
        $this->assertSame(0, $project->movements()->whereIn('type', ['expense', 'income'])->count());
    }

    public function test_transfers_investments_card_payments_and_refunds_can_be_selected_for_the_calendar(): void
    {
        [$project, $owner, $source, $category] = $this->financialProject();
        $savings = $this->account($project, $owner, FinancialAccountType::Savings, 'Ahorro');
        $card = $this->account($project, $owner, FinancialAccountType::CreditCard, 'Tarjeta');
        $investment = $this->account($project, $owner, FinancialAccountType::ExternalInvestment, 'Cartera externa');
        $incomeCategory = $this->category($project, $owner, 'Ingresos', CategoryType::Income);

        $this->actingAs($owner)->get(route('movements.transfer.create', $project))
            ->assertOk()
            ->assertSee('Mostrar en el calendario');
        foreach ([
            [$savings, 'Transferencia visible'],
            [$card, 'Pago de tarjeta visible'],
            [$investment, 'Inversión visible'],
        ] as [$destination, $concept]) {
            $this->actingAs($owner)->post(route('movements.transfer.store', $project), [
                'amount' => '10,00',
                'occurred_on' => '2026-09-15',
                'concept' => $concept,
                'financial_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'show_in_calendar' => '1',
            ])->assertRedirect();
        }
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($source, $incomeCategory, null, [
            'type' => 'income',
            'amount' => '100,00',
            'concept' => 'Ingreso visible',
            'show_in_calendar' => '1',
        ]))->assertRedirect();

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($source, $category, null, [
            'amount' => '40,00',
            'concept' => 'Gasto con devolución',
        ]))->assertRedirect();
        $expense = $project->movements()->where('concept', 'Gasto con devolución')->firstOrFail();
        $this->actingAs($owner)->get(route('movements.refund.create', [$project, $expense]))
            ->assertOk()
            ->assertSee('Mostrar en el calendario');
        $this->actingAs($owner)->post(route('movements.refund.store', [$project, $expense]), [
            'amount' => '10,00',
            'occurred_on' => '2026-09-15',
            'concept' => 'Devolución visible',
            'show_in_calendar' => '1',
        ])->assertRedirect();

        $selected = $project->movements()->whereIn('concept', [
            'Transferencia visible',
            'Pago de tarjeta visible',
            'Inversión visible',
            'Ingreso visible',
            'Devolución visible',
        ])->get();
        $this->assertCount(5, $selected);
        $this->assertTrue($selected->every(fn (Movement $movement): bool => $movement->show_in_calendar));

        $refund = $selected->firstWhere('concept', 'Devolución visible');
        $this->actingAs($owner)->patch(route('movements.update', [$project, $refund]), [
            'amount' => '10,00',
            'occurred_on' => '2026-09-15',
            'concept' => 'Devolución visible',
            'show_in_calendar' => '0',
        ])->assertRedirect();
        $this->assertFalse($refund->fresh()->show_in_calendar);
    }

    public function test_partial_refunds_restore_the_account_and_cannot_exceed_the_original_expense(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, ['amount' => '100']))->assertRedirect();
        $expense = $project->movements()->where('type', 'expense')->firstOrFail();

        $this->actingAs($owner)->post(route('movements.refund.store', [$project, $expense]), [
            'amount' => '30', 'occurred_on' => '2026-09-15', 'concept' => 'Devolución parcial',
        ])->assertRedirect();
        $refund = $project->movements()->where('type', 'refund')->firstOrFail();
        $this->assertDatabaseHas('account_entries', ['movement_id' => $refund->id, 'signed_amount_cents' => 3000]);

        $this->actingAs($owner)->post(route('movements.refund.store', [$project, $expense]), [
            'amount' => '71', 'occurred_on' => '2026-09-15', 'concept' => 'Demasiado',
        ])->assertSessionHasErrors('amount');
        $this->actingAs($owner)->get(route('budgets.index', ['project' => $project, 'month' => '2026-09']))
            ->assertOk()->assertSee('70,00 €');
    }

    public function test_editing_trashing_and_restoring_rebuilds_the_account_effect(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, null, ['amount' => '20']))->assertRedirect();
        $movement = $project->movements()->firstOrFail();

        $this->actingAs($owner)->patch(route('movements.update', [$project, $movement]), $this->movementData($account, $category, null, ['amount' => '35', 'concept' => 'Compra corregida']))->assertRedirect();
        $this->assertDatabaseHas('account_entries', ['movement_id' => $movement->id, 'signed_amount_cents' => -3500]);
        $this->assertDatabaseCount('account_entries', 1);

        $this->actingAs($owner)->delete(route('movements.destroy', [$project, $movement]))->assertRedirect();
        $this->assertDatabaseCount('account_entries', 0);
        $this->assertNotNull($movement->fresh()->purge_at);

        $this->actingAs($owner)->post(route('movements.restore', [$project, $movement]))->assertRedirect();
        $this->assertDatabaseHas('account_entries', ['movement_id' => $movement->id, 'signed_amount_cents' => -3500]);
        $this->assertNull($movement->fresh()->trashed_at);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $movement->id, 'action' => 'restored']);
    }

    public function test_expired_trash_is_permanently_purged_by_the_scheduled_command(): void
    {
        [$project, $owner, $account, $category] = $this->financialProject();
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category))->assertRedirect();
        $movement = $project->movements()->firstOrFail();
        $this->actingAs($owner)->delete(route('movements.destroy', [$project, $movement]))->assertRedirect();
        $movement->update(['purge_at' => now()->subMinute()]);

        $this->artisan('smartwallet:purge-trash')->assertSuccessful();

        $this->assertDatabaseMissing('movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_id' => $movement->id, 'action' => 'purged', 'actor_user_id' => null]);
    }

    /** @return array{Project, User, FinancialAccount, Category, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        $this->membership($project, $owner, $owner, ProjectRole::Owner);
        $account = $this->account($project, $owner, FinancialAccountType::Checking, 'Principal');
        $category = $this->category($project, $owner, 'Alimentación');
        $subcategory = $this->category($project, $owner, 'Supermercado', CategoryType::Expense, $category->id);

        return [$project, $owner, $account, $category, $subcategory];
    }

    private function membership(Project $project, User $user, User $actor, ProjectRole $role): void
    {
        ProjectMember::create([
            'project_id' => $project->id, 'user_id' => $user->id, 'role' => $role,
            'added_by_user_id' => $actor->id, 'joined_at' => now(),
        ]);
    }

    private function account(Project $project, User $owner, FinancialAccountType $type, string $name): FinancialAccount
    {
        return FinancialAccount::create([
            'project_id' => $project->id, 'name' => $name, 'type' => $type, 'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-09-15', 'position' => 10, 'created_by_user_id' => $owner->id,
        ]);
    }

    private function category(Project $project, User $owner, string $name, CategoryType $type = CategoryType::Expense, ?int $parentId = null): Category
    {
        return Category::create([
            'project_id' => $project->id, 'parent_id' => $parentId, 'type' => $type, 'name' => $name,
            'color' => '#147d68', 'icon' => 'cart', 'position' => 10, 'is_initial' => false,
            'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function movementData(FinancialAccount $account, Category $category, ?Category $subcategory = null, array $overrides = []): array
    {
        return array_merge([
            'type' => 'expense', 'amount' => '12,50', 'occurred_on' => '2026-09-15', 'concept' => 'Compra',
            'category_id' => $category->id, 'subcategory_id' => $subcategory?->id,
            'financial_account_id' => $account->id, 'paid_by_user_id' => $account->project->creator_user_id,
            'notes' => null,
        ], $overrides);
    }
}
