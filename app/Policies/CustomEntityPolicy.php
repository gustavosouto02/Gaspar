<?php

namespace App\Policies;

use App\Models\CustomEntity;
use App\Models\User;
use App\Enums\UserRoleEnum;

class CustomEntityPolicy
{
    /**
     * Determina se o usuário pode listar as entidades customizadas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode ver os detalhes de uma entidade.
     */
    public function view(User $user, CustomEntity $customEntity): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode criar entidades.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode atualizar uma entidade.
     */
    public function update(User $user, CustomEntity $customEntity): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode deletar uma entidade.
     */
    public function delete(User $user, CustomEntity $customEntity): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode restaurar uma entidade excluída logicamente.
     */
    public function restore(User $user, CustomEntity $customEntity): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode excluir permanentemente uma entidade.
     */
    public function forceDelete(User $user, CustomEntity $customEntity): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
