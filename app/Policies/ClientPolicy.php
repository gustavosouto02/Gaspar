<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->user_role, [UserRoleEnum::ADMIN, UserRoleEnum::GESTOR]);
    }

    public function update(User $user, Client $client): bool
    {
        return in_array($user->user_role, [UserRoleEnum::ADMIN, UserRoleEnum::GESTOR]);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
