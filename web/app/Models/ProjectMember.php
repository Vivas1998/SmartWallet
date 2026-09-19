<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'user_id',
    'role',
    'added_by_user_id',
    'joined_at',
    'removed_at',
])]
class ProjectMember extends Model
{
    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
