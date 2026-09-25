<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Recurrences\GenerateDueRecurrences;
use App\Enums\CategoryType;
use App\Enums\CustomFieldType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\PlannedMovementStatus;
use App\Enums\ProjectRole;
use App\Enums\RecurrenceFrequency;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\FinancialAccount;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RecurrenceTemplate;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomFieldManagementTest extends TestCase
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

    public function test_new_projects_receive_the_five_approved_empty_examples(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('projects.store'), [
            'name' => 'Casa',
            'description' => null,
            'color' => '#147d68',
            'icon' => 'home',
            'account_name' => 'Principal',
            'account_type' => FinancialAccountType::Checking->value,
            'initial_balance' => '0,00',
            'initial_balance_date' => '2026-09-15',
            'monthly_budget' => '1.000,00',
        ])->assertRedirect();

        $project = Project::query()->sole();
        $definitions = $project->customFieldDefinitions()->orderBy('position')->get();

        $this->assertSame([
            'Número de factura',
            'Fecha de garantía',
            'Gasto deducible',
            'Método de compra',
            'Cubierto por el seguro',
        ], $definitions->pluck('name')->all());
        $this->assertSame(['text', 'date', 'boolean', 'text', 'boolean'], $definitions->pluck('type')->map->value->all());
        $this->assertTrue($definitions->every(fn (CustomFieldDefinition $definition): bool => $definition->is_initial
            && $definition->applicable_movement_types === [MovementType::Expense->value]
            && ! $definition->values()->exists()));
    }

    public function test_only_owners_manage_definitions_and_used_fields_keep_their_history(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();

        $this->actingAs($member)->get(route('custom-fields.index', $project))->assertOk();
        $this->actingAs($member)->post(route('custom-fields.store', $project), $this->definitionData())
            ->assertForbidden();

        $this->actingAs($owner)->post(route('custom-fields.store', $project), $this->definitionData())
            ->assertRedirect();
        $definition = $project->customFieldDefinitions()->sole();
        $this->assertDatabaseHas('audit_logs', [
            'project_id' => $project->id,
            'subject_type' => 'custom_field',
            'subject_id' => $definition->id,
            'action' => 'created',
        ]);

        $this->actingAs($member)->post(route('movements.store', $project), [
            ...$this->expenseData($account, $category, $member),
            'custom_fields' => [$definition->id => 'FAC-2026-001'],
        ])->assertRedirect();

        $this->actingAs($owner)->patch(route('custom-fields.update', [$project, $definition]), [
            ...$this->definitionData(),
            'type' => CustomFieldType::Date->value,
        ])->assertSessionHasErrors('type');
        $this->actingAs($owner)->delete(route('custom-fields.destroy', [$project, $definition]))
            ->assertSessionHasErrors('custom_field');

        $this->actingAs($owner)->post(route('custom-fields.archive', [$project, $definition]))->assertRedirect();
        $this->assertNotNull($definition->fresh()->archived_at);
        $this->actingAs($owner)->patch(route('custom-fields.update', [$project, $definition]), $this->definitionData())
            ->assertSessionHasErrors('custom_field');
        $this->actingAs($owner)->post(route('custom-fields.restore', [$project, $definition]))->assertRedirect();

        $this->assertNull($definition->fresh()->archived_at);
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $definition->id,
            'value_text' => 'FAC-2026-001',
        ]);

        $this->actingAs($owner)->patch(route('custom-fields.update', [$project, $definition]), [
            ...$this->definitionData(),
            'applicable_movement_types' => [MovementType::Income->value],
        ])->assertRedirect();
        $movement = $project->movements()->sole();
        $this->actingAs($member)->patch(route('movements.update', [$project, $movement]), [
            ...$this->expenseData($account, $category, $member),
            'concept' => 'Compra corregida',
        ])->assertRedirect();
        $this->assertDatabaseHas('custom_field_values', [
            'movement_id' => $movement->id,
            'custom_field_definition_id' => $definition->id,
            'value_text' => 'FAC-2026-001',
        ]);
    }

    public function test_members_save_typed_values_and_filter_and_export_them(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $text = $this->definition($project, $owner, 'Factura', CustomFieldType::Text, 1);
        $number = $this->definition($project, $owner, 'Unidades', CustomFieldType::Number, 2);
        $date = $this->definition($project, $owner, 'Garantía', CustomFieldType::Date, 3);
        $boolean = $this->definition($project, $owner, 'Deducible', CustomFieldType::Boolean, 4);

        $this->actingAs($member)->post(route('movements.store', $project), [
            ...$this->expenseData($account, $category, $member),
            'concept' => 'Compra con datos',
            'custom_fields' => [
                $text->id => 'FAC-42',
                $number->id => '12,75',
                $date->id => '2028-09-15',
                $boolean->id => '1',
            ],
        ])->assertRedirect();
        $movement = $project->movements()->sole();

        $this->assertDatabaseHas('custom_field_values', ['movement_id' => $movement->id, 'custom_field_definition_id' => $text->id, 'value_text' => 'FAC-42']);
        $this->assertDatabaseHas('custom_field_values', ['movement_id' => $movement->id, 'custom_field_definition_id' => $number->id, 'value_number' => '12.750000']);
        $this->assertDatabaseHas('custom_field_values', ['movement_id' => $movement->id, 'custom_field_definition_id' => $date->id, 'value_date' => '2028-09-15']);
        $this->assertDatabaseHas('custom_field_values', ['movement_id' => $movement->id, 'custom_field_definition_id' => $boolean->id, 'value_boolean' => true]);
        $this->assertSame('FAC-42', $project->auditLogs()->where('subject_type', 'movement')->latest('id')->firstOrFail()->after_values['custom_fields'][(string) $text->id]['value']);

        $filter = [
            $text->id => ['value' => 'FAC-4'],
            $number->id => ['min' => '12', 'max' => '13'],
            $date->id => ['from' => '2028-01-01', 'to' => '2028-12-31'],
            $boolean->id => ['value' => '1'],
        ];
        $this->actingAs($member)->get(route('movements.index', ['project' => $project, 'custom_filters' => $filter]))
            ->assertOk()
            ->assertSee('Compra con datos')
            ->assertSee('FAC-42');
        $this->actingAs($member)->get(route('movements.index', [
            'project' => $project,
            'custom_filters' => [$text->id => ['value' => '%']],
        ]))->assertOk()->assertDontSee('Compra con datos');

        $csv = $this->actingAs($member)->get(route('movements.export', [
            'project' => $project,
            'scope' => 'filtered',
            'custom_filters' => $filter,
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Campo: Factura', $csv);
        $this->assertStringContainsString('FAC-42', $csv);
        $this->assertStringContainsString('12,75', $csv);
        $this->assertStringContainsString('15/09/2028', $csv);
        $this->assertStringContainsString(';Sí', $csv);
    }

    public function test_archived_and_foreign_fields_cannot_be_added_to_new_movements(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $archived = $this->definition($project, $owner, 'Archivado', CustomFieldType::Text, 1);
        $archived->update(['archived_at' => now(), 'archived_by_user_id' => $owner->id]);
        [$otherProject, $otherOwner] = $this->financialProject();
        $foreign = $this->definition($otherProject, $otherOwner, 'Ajeno', CustomFieldType::Text, 1);

        $this->actingAs($member)->post(route('movements.store', $project), [
            ...$this->expenseData($account, $category, $member),
            'custom_fields' => [$archived->id => 'No permitido'],
        ])->assertSessionHasErrors('custom_fields');
        $this->actingAs($member)->post(route('movements.store', $project), [
            ...$this->expenseData($account, $category, $member),
            'custom_fields' => [$foreign->id => 'No permitido'],
        ])->assertSessionHasErrors('custom_fields');

        $this->assertDatabaseCount('movements', 0);
    }

    public function test_plan_completion_and_recurrence_generation_copy_custom_values(): void
    {
        [$project, $owner, $member, $account, $category] = $this->financialProject();
        $definition = $this->definition($project, $owner, 'Referencia', CustomFieldType::Text, 1);

        $this->actingAs($member)->post(route('planned-movements.store', $project), [
            'movement_kind' => 'expense',
            'amount' => '50,00',
            'due_on' => '2026-09-15',
            'concept' => 'Seguro previsto',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $member->id,
            'custom_fields' => [$definition->id => 'PLAN-1'],
        ])->assertRedirect();
        $plan = PlannedMovement::query()->sole();

        $this->actingAs($member)->post(route('planned-movements.complete.store', [$project, $plan]), [
            'amount' => '51,00',
            'occurred_on' => '2026-09-15',
            'concept' => 'Seguro real',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $member->id,
            'custom_fields' => [$definition->id => 'PLAN-REAL'],
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame(PlannedMovementStatus::Completed, $plan->status);
        $this->assertDatabaseHas('custom_field_values', ['movement_id' => $plan->movement_id, 'custom_field_definition_id' => $definition->id, 'value_text' => 'PLAN-REAL']);

        $recurrence = RecurrenceTemplate::create([
            'project_id' => $project->id,
            'type' => MovementType::Expense,
            'amount_cents' => 1250,
            'concept' => 'Cuota',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $owner->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'start_on' => '2026-09-15',
            'anchor_day' => 15,
            'next_occurrence_on' => '2026-09-15',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $recurrence->customFieldValues()->create([
            'custom_field_definition_id' => $definition->id,
            'value_text' => 'SERIE-1',
        ]);

        app(GenerateDueRecurrences::class)->handle(CarbonImmutable::parse('2026-09-15'));

        $generated = $project->movements()->where('recurrence_template_id', $recurrence->id)->sole();
        $this->assertDatabaseHas('custom_field_values', ['movement_id' => $generated->id, 'custom_field_definition_id' => $definition->id, 'value_text' => 'SERIE-1']);
    }

    /** @return array{Project, User, User, FinancialAccount, Category} */
    private function financialProject(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create(['name' => 'Casa familiar']);
        $this->membership($project, $owner, $owner, ProjectRole::Owner);
        $this->membership($project, $member, $owner, ProjectRole::Member);
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

    /** @return array<string, mixed> */
    private function expenseData(FinancialAccount $account, Category $category, User $payer): array
    {
        return [
            'type' => MovementType::Expense->value,
            'amount' => '12,50',
            'occurred_on' => '2026-09-15',
            'concept' => 'Compra',
            'category_id' => $category->id,
            'financial_account_id' => $account->id,
            'paid_by_user_id' => $payer->id,
        ];
    }

    /** @return array<string, mixed> */
    private function definitionData(): array
    {
        return [
            'name' => 'Número de factura',
            'type' => CustomFieldType::Text->value,
            'applicable_movement_types' => [MovementType::Expense->value],
        ];
    }

    private function definition(
        Project $project,
        User $owner,
        string $name,
        CustomFieldType $type,
        int $position,
    ): CustomFieldDefinition {
        return CustomFieldDefinition::create([
            'project_id' => $project->id,
            'name' => $name,
            'name_normalized' => Str::lower($name),
            'type' => $type,
            'applicable_movement_types' => [MovementType::Expense->value],
            'position' => $position,
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
