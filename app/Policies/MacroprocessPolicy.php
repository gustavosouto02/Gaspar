<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Macroprocess;
use App\Models\User;

class MacroprocessPolicy
{
    /**
     * Determina se o usuário pode ver a listagem de macroprocessos.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode visualizar os detalhes de um macroprocesso.
     */
    public function view(User $user, Macroprocess $macroprocess): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode criar novos macroprocessos.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode editar um macroprocesso.
     */
    public function update(User $user, Macroprocess $macroprocess): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode excluir um macroprocesso.
     */
    public function delete(User $user, Macroprocess $macroprocess): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode restaurar um macroprocesso excluído.
     */
    public function restore(User $user, Macroprocess $macroprocess): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode forçar a exclusão de um macroprocesso.
     */
    public function forceDelete(User $user, Macroprocess $macroprocess): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
