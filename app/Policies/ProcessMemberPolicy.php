<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\ProcessMember;
use App\Models\User;

class ProcessMemberPolicy
{
    /**
     * Todos os usuários autenticados podem ver a lista de membros.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Todos os usuários autenticados podem visualizar um membro específico.
     */
    public function view(User $user, ProcessMember $processMember): bool
    {
        return true;
    }

    /**
     * Apenas administradores podem adicionar membros ao processo.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Apenas administradores podem editar membros.
     */
    public function update(User $user, ProcessMember $processMember): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Apenas administradores podem remover membros.
     */
    public function delete(User $user, ProcessMember $processMember): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
