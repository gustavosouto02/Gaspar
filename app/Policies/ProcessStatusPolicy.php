<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\ProcessStatus;
use App\Models\User;

class ProcessStatusPolicy
{
    /**
     * Todos os usuários autenticados podem ver a lista de situações.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Todos os usuários autenticados podem visualizar uma situação específica.
     */
    public function view(User $user, ProcessStatus $processStatus): bool
    {
        return true;
    }

    /**
     * Apenas administradores podem criar situações.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Apenas administradores podem editar situações.
     */
    public function update(User $user, ProcessStatus $processStatus): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Apenas administradores podem excluir situações.
     */
    public function delete(User $user, ProcessStatus $processStatus): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
