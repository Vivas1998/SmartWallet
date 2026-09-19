<?php

declare(strict_types=1);

namespace App\Actions\ProjectMembers;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProjectMemberRole
{
    public function handle(Project $project, ProjectMember $membership, ProjectRole $role): void
    {
        DB::transaction(function () use ($project, $membership, $role): void {
            $lockedMembership = ProjectMember::query()->lockForUpdate()->findOrFail($membership->id);

            if ($lockedMembership->removed_at !== null) {
                throw ValidationException::withMessages([
                    'role' => 'No se puede cambiar el rol de una persona retirada.',
                ]);
            }

            if ($lockedMembership->user_id === $project->creator_user_id && $role !== ProjectRole::Owner) {
                throw ValidationException::withMessages([
                    'role' => 'La persona creadora del proyecto debe conservar el rol de propietario.',
                ]);
            }

            if ($lockedMembership->role === ProjectRole::Owner && $role === ProjectRole::Member) {
                $ownerCount = ProjectMember::query()
                    ->where('project_id', $project->id)
                    ->where('role', ProjectRole::Owner->value)
                    ->whereNull('removed_at')
                    ->lockForUpdate()
                    ->count();

                if ($ownerCount <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'El proyecto debe conservar al menos un propietario.',
                    ]);
                }
            }

            $lockedMembership->update(['role' => $role]);
        });
    }
}
