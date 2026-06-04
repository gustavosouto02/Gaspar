<?php

namespace App\Policies;

use App\Models\CustomField;
use App\Models\User;
use App\Enums\UserRoleEnum;

class CustomFieldPolicy
{
    /**
     * Determina se o usuário pode ver a lista de campos.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode visualizar um campo.
     */
    public function view(User $user, CustomField $customField): bool
    {
        return true;
    }

    /**
     * Determina se o usuário pode criar campos.
     */
    public function create(User $user): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode atualizar um campo.
     */
    public function update(User $user, CustomField $customField): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode excluir um campo.
     */
    public function delete(User $user, CustomField $customField): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode restaurar um campo excluído logicamente.
     */
    public function restore(User $user, CustomField $customField): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Determina se o usuário pode excluir permanentemente um campo.
     */
    public function forceDelete(User $user, CustomField $customField): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
