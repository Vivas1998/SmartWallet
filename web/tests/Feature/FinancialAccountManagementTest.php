<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FinancialAccountType;
use App\Enums\ProjectRole;
use App\Models\FinancialAccount;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_owner_can_create_edit_and_archive_accounts(): void
    {
        [$project, $owner, $member] = $this->projectWithMember();

        $this->actingAs($member)->post(route('financial-accounts.store', $project), $this->accountData())->assertForbidden();
        $this->actingAs($owner)->post(route('financial-accounts.store', $project), $this->accountData())->assertRedirect();
        $account = $project->financialAccounts()->firstOrFail();

        $this->actingAs($member)->patch(route('financial-accounts.update', [$project, $account]), $this->accountData(['name' => 'Ajena']))->assertForbidden();
        $this->actingAs($owner)->patch(route('financial-accounts.update', [$project, $account]), $this->accountData(['name' => 'Ahorro familiar']))->assertRedirect();
        $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'name' => 'Ahorro familiar']);

        $this->actingAs($owner)->post(route('financial-accounts.archive', [$project, $account]))->assertRedirect();
        $this->assertNotNull($account->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['project_id' => $project->id, 'subject_id' => $account->id, 'subject_type' => 'account', 'action' => 'archived']);
    }

    public function test_an_account_from_another_project_cannot_be_changed(): void
    {
        [$project, $owner] = $this->projectWithMember();
        [$otherProject, $otherOwner] = $this->projectWithMember();
        $foreign = FinancialAccount::create([
            'project_id' => $otherProject->id,
            'name' => 'Otra',
            'type' => FinancialAccountType::Checking,
            'initial_balance_cents' => 0,
            'initial_balance_date' => '2026-09-15',
            'created_by_user_id' => $otherOwner->id,
        ]);

        $this->actingAs($owner)->patch(route('financial-accounts.update', [$project, $foreign]), $this->accountData())->assertNotFound();
    }

    /** @return array{Project, User, User} */
    private function projectWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => ProjectRole::Owner, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::Member, 'added_by_user_id' => $owner->id, 'joined_at' => now()]);

        return [$project, $owner, $member];
    }

    /** @return array<string, string> */
    private function accountData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ahorro',
            'type' => 'savings',
            'initial_balance' => '500,00',
            'initial_balance_date' => '2026-09-15',
            'credit_limit' => '',
            'color' => '#147d68',
            'icon' => 'savings',
        ], $overrides);
    }
}
