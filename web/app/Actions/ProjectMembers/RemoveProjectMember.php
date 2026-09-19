<?php

declare(strict_types=1);

namespace App\Actions\ProjectMembers;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveProjectMember
{
    public function handle(Project $project, ProjectMember $membership): void
    {
        DB::transaction(function () use ($project, $membership): void {
            $lockedMembership = ProjectMember::query()->lockForUpdate()->findOrFail($membership->id);

            if ($lockedMembership->removed_at !== null) {
                throw ValidationException::withMessages([
                    'member' => 'Esta persona ya no pertenece al proyecto.',
                ]);
            }

            if ($lockedMembership->user_id === $project->creator_user_id) {
                throw ValidationException::withMessages([
                    'member' => 'La persona creadora del proyecto no puede ser retirada.',
                ]);
            }

            if ($lockedMembership->role === ProjectRole::Owner) {
                $ownerCount = ProjectMember::query()
                    ->where('project_id', $project->id)
                    ->where('role', ProjectRole::Owner->value)
                    ->whereNull('removed_at')
                    ->lockForUpdate()
                    ->count();

                if ($ownerCount <= 1) {
                    throw ValidationException::withMessages([
                        'member' => 'El proyecto debe conservar al menos un propietario.',
                    ]);
                }
            }

            $lockedMembership->update(['removed_at' => now()]);
        });
    }
}
