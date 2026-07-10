<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\ProcessRole;
use App\Models\User;

class ProcessRolePolicy
{
    /**
     * Todos os usuários autenticados podem ver a lista de papéis.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Todos os usuários autenticados podem visualizar um papel específico.
     */
    public function view(User $user, ProcessRole $processRole): bool
    {
        return true;
    }

    /**
     * Apenas administradores podem criar papéis.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Apenas administradores podem editar papéis.
     */
    public function update(User $user, ProcessRole $processRole): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Apenas administradores podem excluir papéis.
     */
    public function delete(User $user, ProcessRole $processRole): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
