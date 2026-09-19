<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\ProjectMembers\AddProjectMember;
use App\Actions\ProjectMembers\RemoveProjectMember;
use App\Actions\ProjectMembers\UpdateProjectMemberRole;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $memberships = $project->activeMemberships()
            ->with('user')
            ->orderByRaw('user_id = ? desc', [$project->creator_user_id])
            ->orderByRaw("role = 'owner' desc")
            ->orderBy('joined_at')
            ->get();

        return view('project-members.index', [
            'project' => $project,
            'memberships' => $memberships,
            'canManage' => $request->user()->can('manageMembers', $project),
        ]);
    }

    public function store(
        Request $request,
        Project $project,
        AddProjectMember $addProjectMember,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ]);

        $membership = $addProjectMember->handle($project, $request->user(), $validated['email']);
        $audit->handle($project, $request->user(), 'member', $membership->id, 'created', null, $this->snapshot($membership));

        return redirect()
            ->route('project-members.index', $project)
            ->with('status', $membership->user->name.' ya forma parte del proyecto.');
    }

    public function update(
        Request $request,
        Project $project,
        int $membership,
        UpdateProjectMemberRole $updateProjectMemberRole,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ]);

        $projectMembership = $this->membershipForProject($project, $membership);
        $before = $this->snapshot($projectMembership);
        $role = ProjectRole::from($validated['role']);
        $updateProjectMemberRole->handle($project, $projectMembership, $role);
        $audit->handle($project, $request->user(), 'member', $projectMembership->id, 'updated', $before, $this->snapshot($projectMembership->fresh()));

        return redirect()
            ->route('project-members.index', $project)
            ->with('status', 'Rol actualizado correctamente.');
    }

    public function destroy(
        Request $request,
        Project $project,
        int $membership,
        RemoveProjectMember $removeProjectMember,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageMembers', $project);

        $projectMembership = $this->membershipForProject($project, $membership);
        $before = $this->snapshot($projectMembership);
        $removedCurrentUser = $projectMembership->user_id === $request->user()->id;
        $removeProjectMember->handle($project, $projectMembership);
        $audit->handle($project, $request->user(), 'member', $projectMembership->id, 'member_removed', $before, $this->snapshot($projectMembership->fresh()));

        if ($removedCurrentUser) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Has salido del proyecto. Se conserva tu atribución histórica.');
        }

        return redirect()
            ->route('project-members.index', $project)
            ->with('status', 'Acceso retirado. Se conserva la atribución histórica de la persona.');
    }

    private function membershipForProject(Project $project, int $membership): ProjectMember
    {
        return $project->memberships()->findOrFail($membership);
    }

    /** @return array<string, mixed> */
    private function snapshot(ProjectMember $membership): array
    {
        return [
            'user_id' => $membership->user_id,
            'role' => $membership->role->value,
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'removed_at' => $membership->removed_at?->toIso8601String(),
        ];
    }
}
