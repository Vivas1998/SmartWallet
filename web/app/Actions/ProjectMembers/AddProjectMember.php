<?php

declare(strict_types=1);

namespace App\Actions\ProjectMembers;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AddProjectMember
{
    public function handle(Project $project, User $actor, string $email): ProjectMember
    {
        $user = User::query()
            ->where('email_normalized', Str::lower(trim($email)))
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'No existe ninguna cuenta con ese correo electrónico.',
            ]);
        }

        return DB::transaction(function () use ($project, $actor, $user): ProjectMember {
            $membership = ProjectMember::query()
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership !== null && $membership->removed_at === null) {
                throw ValidationException::withMessages([
                    'email' => 'Esta persona ya pertenece al proyecto.',
                ]);
            }

            if ($membership !== null) {
                $membership->update([
                    'role' => ProjectRole::Member,
                    'added_by_user_id' => $actor->id,
                    'joined_at' => now(),
                    'removed_at' => null,
                ]);

                return $membership->refresh();
            }

            return ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => ProjectRole::Member,
                'added_by_user_id' => $actor->id,
                'joined_at' => now(),
            ]);
        });
    }
}
