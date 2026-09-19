<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_add_an_existing_account_as_member(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $relative = User::factory()->create(['email' => 'Familiar@Example.com']);

        $response = $this->actingAs($owner)->post(route('project-members.store', $project), [
            'email' => ' familiar@example.com ',
        ]);

        $response->assertRedirect(route('project-members.index', $project));
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $relative->id,
            'role' => ProjectRole::Member->value,
            'added_by_user_id' => $owner->id,
            'removed_at' => null,
        ]);
    }

    public function test_only_an_existing_account_can_be_added_and_duplicates_are_rejected(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->actingAs($owner)
            ->post(route('project-members.store', $project), ['email' => 'nadie@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->post(route('project-members.store', $project), ['email' => $owner->email])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('project_members', 1);
    }

    public function test_a_removed_person_is_reactivated_as_member_using_the_same_membership(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $relative = User::factory()->create();
        $membership = ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $relative->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $owner->id,
            'joined_at' => now()->subMonth(),
            'removed_at' => now()->subWeek(),
        ]);

        $this->actingAs($owner)->post(route('project-members.store', $project), [
            'email' => $relative->email,
        ])->assertRedirect(route('project-members.index', $project));

        $membership->refresh();

        $this->assertSame(ProjectRole::Member, $membership->role);
        $this->assertNull($membership->removed_at);
        $this->assertSame($owner->id, $membership->added_by_user_id);
        $this->assertDatabaseCount('project_members', 2);
    }

    public function test_a_member_can_view_people_but_cannot_manage_them(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $member = User::factory()->create();
        $membership = $this->addMembership($project, $member, $owner, ProjectRole::Member);
        $candidate = User::factory()->create();

        $this->actingAs($member)
            ->get(route('project-members.index', $project))
            ->assertOk()
            ->assertSee($owner->name)
            ->assertSee($member->name);

        $this->actingAs($member)
            ->post(route('project-members.store', $project), ['email' => $candidate->email])
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('project-members.update', [$project, $membership]), ['role' => 'owner'])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('project-members.destroy', [$project, $membership]))
            ->assertForbidden();
    }

    public function test_the_project_creator_cannot_be_demoted_or_removed(): void
    {
        [$project, $creator] = $this->projectWithOwner();
        $creatorMembership = $project->memberships()->where('user_id', $creator->id)->sole();

        $this->actingAs($creator)
            ->patch(route('project-members.update', [$project, $creatorMembership]), ['role' => 'member'])
            ->assertSessionHasErrors('role');

        $this->actingAs($creator)
            ->delete(route('project-members.destroy', [$project, $creatorMembership]))
            ->assertSessionHasErrors('member');

        $creatorMembership->refresh();
        $this->assertSame(ProjectRole::Owner, $creatorMembership->role);
        $this->assertNull($creatorMembership->removed_at);
    }

    public function test_an_owner_can_promote_demote_and_remove_another_person(): void
    {
        [$project, $creator] = $this->projectWithOwner();
        $relative = User::factory()->create();
        $membership = $this->addMembership($project, $relative, $creator, ProjectRole::Member);

        $this->actingAs($creator)
            ->patch(route('project-members.update', [$project, $membership]), ['role' => 'owner'])
            ->assertRedirect(route('project-members.index', $project));
        $this->assertSame(ProjectRole::Owner, $membership->refresh()->role);

        $this->actingAs($creator)
            ->patch(route('project-members.update', [$project, $membership]), ['role' => 'member'])
            ->assertRedirect(route('project-members.index', $project));
        $this->assertSame(ProjectRole::Member, $membership->refresh()->role);

        $this->actingAs($creator)
            ->delete(route('project-members.destroy', [$project, $membership]))
            ->assertRedirect(route('project-members.index', $project));
        $this->assertNotNull($membership->refresh()->removed_at);
    }

    /**
     * @return array{Project, User}
     */
    private function projectWithOwner(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'creator')->create();

        $this->addMembership($project, $owner, $owner, ProjectRole::Owner);

        return [$project, $owner];
    }

    private function addMembership(
        Project $project,
        User $user,
        User $actor,
        ProjectRole $role,
    ): ProjectMember {
        return ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'added_by_user_id' => $actor->id,
            'joined_at' => now(),
        ]);
    }
}
