<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->activeMemberships()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && $project->isArchived();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function manageCategories(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function manageBudgets(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function manageAccounts(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function manageRecurrences(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function manageSavingsGoals(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function contributeToSavingsGoals(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ! $project->isArchived();
    }

    public function createTags(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ! $project->isArchived();
    }

    public function manageTags(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function manageCustomFields(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project) && ! $project->isArchived();
    }

    public function exportTrash(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project);
    }

    public function recordMovements(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ! $project->isArchived();
    }

    public function managePlannedMovements(User $user, Project $project): bool
    {
        return $this->recordMovements($user, $project);
    }

    private function isOwner(User $user, Project $project): bool
    {
        return $project->activeMemberships()
            ->where('user_id', $user->id)
            ->where('role', ProjectRole::Owner->value)
            ->exists();
    }
}
