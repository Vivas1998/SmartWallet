<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_each_projects_last_activity_and_a_scoped_recent_feed(): void
    {
        $owner = User::factory()->create(['name' => 'Lucía Martín']);
        $other = User::factory()->create(['name' => 'Persona ajena']);
        $family = $this->projectWithOwner($owner, 'Economía familiar');
        $personal = $this->projectWithOwner($owner, 'Finanzas personales');
        $hidden = $this->projectWithOwner($other, 'Proyecto privado ajeno');

        AuditLog::create([
            'project_id' => $family->id,
            'actor_user_id' => $owner->id,
            'subject_type' => 'movement',
            'subject_id' => 10,
            'action' => 'updated',
            'created_at' => '2026-09-12 10:00:00',
        ]);
        AuditLog::create([
            'project_id' => $personal->id,
            'actor_user_id' => null,
            'subject_type' => 'recurrence',
            'subject_id' => 20,
            'action' => 'generated',
            'created_at' => '2026-09-13 10:00:00',
        ]);
        AuditLog::create([
            'project_id' => $hidden->id,
            'actor_user_id' => $other->id,
            'subject_type' => 'movement',
            'subject_id' => 30,
            'action' => 'created',
            'created_at' => '2026-09-14 10:00:00',
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Actividad reciente')
            ->assertSee('Última actividad')
            ->assertSee('Lucía Martín')
            ->assertSee('modificó un movimiento')
            ->assertSee('Sistema')
            ->assertSee('generó automáticamente una serie recurrente')
            ->assertSeeInOrder(['Finanzas personales', 'Economía familiar'])
            ->assertDontSee('Proyecto privado ajeno')
            ->assertDontSee('Persona ajena');
    }

    public function test_project_switcher_lists_only_active_memberships_and_marks_archived_projects(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $current = $this->projectWithOwner($owner, 'Casa');
        $archived = $this->projectWithOwner($owner, 'Vacaciones', ['archived_at' => now()]);
        $hidden = $this->projectWithOwner($other, 'Negocio ajeno');
        $removed = $this->projectWithOwner($owner, 'Proyecto retirado');
        $removed->memberships()->where('user_id', $owner->id)->update(['removed_at' => now()]);

        $response = $this->actingAs($owner)->get(route('projects.settings', $current));

        $response->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content" tabindex="-1"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Proyecto actual')
            ->assertSee('data-project-switcher', false)
            ->assertSee(route('projects.show', $current), false)
            ->assertSee(route('projects.show', $archived), false)
            ->assertSee('Vacaciones (archivado)')
            ->assertDontSee('Negocio ajeno')
            ->assertDontSee('Proyecto retirado')
            ->assertDontSee(route('projects.show', $hidden), false)
            ->assertSee('Ver mis proyectos');
    }

    /** @param array<string, mixed> $attributes */
    private function projectWithOwner(User $owner, string $name, array $attributes = []): Project
    {
        $project = Project::factory()->for($owner, 'creator')->create(array_merge([
            'name' => $name,
        ], $attributes));

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::Owner,
            'added_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        return $project;
    }
}
