<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRoleEnum;

class UserPolicy
{
    /**
     * Determina se o usuário autenticado pode ver a lista de usuários.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode visualizar os detalhes específicos de um usuário específico
     */
    public function view(User $user, User $model): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode criar novos registros de usuários no sistema.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode editar/atualizar um usuário específico.
     */
    public function update(User $user, User $model): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode excluir um usuário específico do banco de dados 
     */
    public function delete(User $user, User $model): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     *  Determina se o usuário pode restaurar um usuário que foi excluído logicamente do banco de dados.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode excluir permanentemente (destruir do banco físico) um usuário que já estava na lixeira (Soft Deleted).
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
