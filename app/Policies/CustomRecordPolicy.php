<?php

namespace App\Policies;

use App\Models\CustomRecord;
use App\Models\User;
use App\Enums\UserRoleEnum;

class CustomRecordPolicy
{
    /**
     * Determina se o usuário pode ver a lista de registros.
     */
    public function viewAny(User $user): bool
    {
        return true; // Todos os usuários autenticados podem ver a lista
    }

    /**
     * Determina se o usuário pode visualizar um registro específico.
     */
    public function view(User $user, CustomRecord $customRecord): bool
    {
        return true; // Todos podem visualizar qualquer registro
    }

    /**
     * Determina se o usuário pode criar novos registros.
     */
    public function create(User $user): bool
    {
        return true; // Todos os usuários autenticados podem preencher fichas
    }

    /**
     * Determina se o usuário pode editar/atualizar um registro.
     */
    public function update(User $user, CustomRecord $customRecord): bool
    {
        // Administrador pode editar qualquer registro
        if ($user->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        // Usuários normais só podem editar se foram os criadores do registro
        return $customRecord->created_by === $user->id;
    }

    /**
     * Determina se o usuário pode excluir um registro.
     */
    public function delete(User $user, CustomRecord $customRecord): bool
    {
        // Administrador pode excluir qualquer registro
        if ($user->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        // Usuários normais só podem excluir se foram os criadores do registro
        return $customRecord->created_by === $user->id;
    }

    /**
     * Determina se o usuário pode restaurar um registro excluído logicamente.
     */
    public function restore(User $user, CustomRecord $customRecord): bool
    {
        if ($user->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        return $customRecord->created_by === $user->id;
    }

    /**
     * Determina se o usuário pode excluir permanentemente um registro.
     */
    public function forceDelete(User $user, CustomRecord $customRecord): bool
    {
        if ($user->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        return $customRecord->created_by === $user->id;
    }
}
