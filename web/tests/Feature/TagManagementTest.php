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
use App\Models\Movement;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RecurrenceTemplate;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_create_tags_but_only_owners_manage_them(): void
    {
        [$project, $owner, $member] = $this->projectWithMember();

        $this->actingAs($member)->post(route('tags.store', $project), ['name' => '  Navidad  '])->assertRedirect();
        $tag = $project->tags()->firstOrFail();
        $this->assertSame('Navidad', $tag->name);
        $this->assertSame('navidad', $tag->name_normalized);
        $this->actingAs($member)->get(route('tags.index', $project))->assertOk()->assertSee('# Navidad');

        $this->actingAs($member)->patch(route('tags.update', [$project, $tag]), ['name' => 'Fiestas'])->assertForbidden();
        $this->actingAs($owner)->patch(route('tags.update', [$project, $tag]), ['name' => 'Fiestas'])->assertRedirect();
        $this->actingAs($owner)->post(route('tags.archive', [$project, $tag]))->assertRedirect();
        $this->assertNotNull($tag->fresh()->archived_at);
        $this->actingAs($owner)->post(route('tags.restore', [$project, $tag]))->assertRedirect();
        $this->assertNull($tag->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_type' => 'tag', 'action' => 'updated']);
    }

    public function test_movements_accept_multiple_project_tags_and_reject_foreign_or_archived_new_tags(): void
    {
        [$project, $owner, , $account, $category] = $this->projectWithMember();
        $travel = $this->tag($project, $owner, 'Viaje');
        $work = $this->tag($project, $owner, 'Trabajo');
        $this->actingAs($owner)->get(route('movements.create', $project))->assertOk()->assertSee('# Viaje');
        $this->actingAs($owner)->get(route('recurrences.create', $project))->assertOk()->assertSee('# Trabajo');

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, [$travel->id, $work->id]))->assertRedirect();
        $movement = $project->movements()->firstOrFail();
        $this->assertEqualsCanonicalizing([$travel->id, $work->id], $movement->tags()->pluck('tags.id')->all());

        $travel->update(['archived_at' => now()]);
        $this->actingAs($owner)->patch(route('movements.update', [$project, $movement]), $this->movementData($account, $category, [$travel->id], ['concept' => 'Compra corregida']))->assertRedirect();
        $this->assertTrue($movement->fresh()->tags->contains($travel));

        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, [$travel->id], ['concept' => 'Movimiento nuevo']))->assertSessionHasErrors('tag_ids');

        [$otherProject, $otherOwner] = $this->projectWithMember();
        $foreign = $this->tag($otherProject, $otherOwner, 'Ajena');
        $this->actingAs($owner)->post(route('movements.store', $project), $this->movementData($account, $category, [$foreign->id], ['concept' => 'Etiqueta ajena']))->assertSessionHasErrors('tag_ids');
    }

    public function test_an_owner_can_merge_tags_without_duplicating_relations(): void
    {
        [$project, $owner, , $account, $category] = $this->projectWithMember();
        $source = $this->tag($project, $owner, 'Vacaciones');
        $target = $this->tag($project, $owner, 'Viajes');
        $movement = $this->movement($project, $owner, $account, $category);
        $template = $this->template($project, $owner, $account, $category, '2027-01-01');
        $movement->tags()->attach([$source->id, $target->id]);
        $template->tags()->attach($source);

        $this->actingAs($owner)->post(route('tags.merge', [$project, $source]), ['target_tag_id' => $target->id])->assertRedirect();

        $this->assertEquals([$target->id], $movement->tags()->pluck('tags.id')->all());
        $this->assertEquals([$target->id], $template->tags()->pluck('tags.id')->all());
        $this->assertSame($target->id, $source->fresh()->merged_into_tag_id);
        $this->assertNotNull($source->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'tag', 'subject_id' => $source->id, 'action' => 'merged']);
    }

    public function test_generated_occurrences_copy_the_series_tags(): void
    {
        [$project, $owner, , $account, $category] = $this->projectWithMember();
        $tag = $this->tag($project, $owner, 'Fijo');
        $template = $this->template($project, $owner, $account, $category, '2026-09-15');
        $template->tags()->attach($tag);

        app(GenerateDueRecurrences::class)->handle(CarbonImmutable::parse('2026-09-15', 'Europe/Madrid'));

        $movement = $project->movements()->firstOrFail();
        $this->assertTrue($movement->tags->contains($tag));
    }

    /** @return array{Project, User, User, FinancialAccount, Category} */
    private function projectWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => ProjectRole::Owner, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::Member, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        $account = FinancialAccount::create(['project_id' => $project->id, 'name' => 'Principal', 'type' => FinancialAccountType::Checking, 'initial_balance_cents' => 0, 'initial_balance_date' => '2026-01-01', 'position' => 10, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
        $category = Category::create(['project_id' => $project->id, 'type' => CategoryType::Expense, 'name' => 'Alimentación', 'color' => '#147d68', 'icon' => 'cart', 'position' => 10, 'is_initial' => false, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);

        return [$project, $owner, $member, $account, $category];
    }

    private function tag(Project $project, User $owner, string $name): Tag
    {
        return Tag::create(['project_id' => $project->id, 'name' => $name, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    /** @param list<int> $tagIds @param array<string, mixed> $overrides */
    private function movementData(FinancialAccount $account, Category $category, array $tagIds, array $overrides = []): array
    {
        return array_merge(['type' => 'expense', 'amount' => '12,50', 'occurred_on' => '2026-09-15', 'concept' => 'Compra', 'category_id' => $category->id, 'financial_account_id' => $account->id, 'paid_by_user_id' => $account->project->creator_user_id, 'tag_ids' => $tagIds], $overrides);
    }

    private function movement(Project $project, User $owner, FinancialAccount $account, Category $category): Movement
    {
        return Movement::create(['project_id' => $project->id, 'type' => MovementType::Expense, 'amount_cents' => 1250, 'occurred_on' => '2026-09-15', 'concept' => 'Compra', 'category_id' => $category->id, 'financial_account_id' => $account->id, 'paid_by_user_id' => $owner->id, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }

    private function template(Project $project, User $owner, FinancialAccount $account, Category $category, string $next): RecurrenceTemplate
    {
        return RecurrenceTemplate::create(['project_id' => $project->id, 'type' => MovementType::Expense, 'amount_cents' => 1250, 'concept' => 'Compra habitual', 'category_id' => $category->id, 'financial_account_id' => $account->id, 'paid_by_user_id' => $owner->id, 'frequency' => RecurrenceFrequency::Monthly, 'start_on' => $next, 'anchor_day' => 15, 'next_occurrence_on' => $next, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id]);
    }
}
