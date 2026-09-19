<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;

final class RecordProjectAudit
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function handle(
        Project $project,
        ?User $actor,
        string $subjectType,
        int $subjectId,
        string $action,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        return AuditLog::create([
            'project_id' => $project->id,
            'actor_user_id' => $actor?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
        ]);
    }
}
