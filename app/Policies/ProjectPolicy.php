<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->user_role, [UserRoleEnum::ADMIN, UserRoleEnum::GESTOR]);
    }

    public function update(User $user, Project $project): bool
    {
        return in_array($user->user_role, [UserRoleEnum::ADMIN, UserRoleEnum::GESTOR]);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
