<?php

namespace App\Services;

use App\Enums\FieldPermissionEnum;
use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Models\FieldPermission;
use App\Models\ProcessMember;
use App\Models\ProcessStatus;
use App\Models\User;

/**
 * Resolve a permissão efetiva de cada campo do processo para uma situação
 * e um usuário. Precedência: linha do papel vence a genérica; entre vários
 * papéis vale a mais permissiva; sem linha vale o fallback (is_required em
 * situações abertas, somente leitura em situações finais); ADMIN nunca
 * fica abaixo de OPTIONAL.
 */
class FieldPermissionResolver
{
    /**
     * @return array<string, FieldPermissionEnum> mapa custom_field_id => permissão efetiva
     */
    public function resolve(CustomEntity $entity, ProcessStatus $status, User $user): array
    {
        $fields = $entity->fields()->get();

        $roleIds = ProcessMember::where('entity_id', $entity->id)
            ->where('user_id', $user->id)
            ->pluck('process_role_id')
            ->all();

        $rows = FieldPermission::where('entity_id', $entity->id)
            ->where('process_status_id', $status->id)
            ->where(function ($q) use ($roleIds) {
                $q->whereNull('process_role_id')
                    ->orWhereIn('process_role_id', $roleIds);
            })
            ->get()
            ->groupBy('custom_field_id');

        $isFinalStatus = $status->isClosed() || $status->isEvaluated() || $status->isCanceled();
        $isAdmin       = $user->user_role === UserRoleEnum::ADMIN;

        $permissions = [];

        foreach ($fields as $field) {
            $fieldRows = $rows->get($field->id, collect());
            $roleRows  = $fieldRows->whereNotNull('process_role_id');
            $generic   = $fieldRows->whereNull('process_role_id')->first();

            if ($roleRows->isNotEmpty()) {
                // Usuário com vários papéis recebe a mais permissiva
                $permission = $roleRows
                    ->map(fn (FieldPermission $row) => $row->permission)
                    ->sortByDesc(fn (FieldPermissionEnum $p) => $p->rank())
                    ->first();
            } elseif ($generic) {
                $permission = $generic->permission;
            } elseif ($isFinalStatus) {
                $permission = FieldPermissionEnum::READONLY;
            } else {
                $permission = $field->is_required
                    ? FieldPermissionEnum::REQUIRED
                    : FieldPermissionEnum::OPTIONAL;
            }

            // ADMIN sempre recebe no mínimo OPTIONAL: nunca fica bloqueado pela matriz
            if ($isAdmin && $permission->rank() < FieldPermissionEnum::OPTIONAL->rank()) {
                $permission = FieldPermissionEnum::OPTIONAL;
            }

            $permissions[$field->id] = $permission;
        }

        return $permissions;
    }
}
