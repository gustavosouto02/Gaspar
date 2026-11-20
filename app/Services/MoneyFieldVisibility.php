<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra de visibilidade dos campos de valor (FieldTypeEnum::CURRENCY) em
 * consultas e relatórios (relatório de demandas, PDF, busca e listagens dos
 * cadastros). Formulários e telas de edição/visualização não são restritos.
 *
 * Quem pode ver um valor depende do "dono" do campo:
 * - ADMIN vê sempre;
 * - Processo (CustomEntity): o gestor do processo ou o gestor do macroprocesso
 *   ao qual ele pertence;
 * - Cadastro (CustomRecordType): qualquer um dos gestores do cadastro;
 * - os gestores precisam continuar com perfil GESTOR e ativos (quem virou
 *   EXECUTOR ou foi desativado deixa de ver, mesmo ainda constando como gestor);
 * - qualquer outro caso (inclusive sem usuário autenticado): ninguém.
 */
class MoneyFieldVisibility
{
    public function canView(?User $user, ?Model $owner): bool
    {
        if (! $user || ! $owner) {
            return false;
        }

        if ($user->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        if (! $this->isActiveManager($user)) {
            return false;
        }

        if ($owner instanceof CustomEntity) {
            return $owner->manager_id === $user->id
                || ($owner->macroprocess_id !== null
                    && Macroprocess::whereKey($owner->macroprocess_id)
                        ->where('manager_id', $user->id)
                        ->exists());
        }

        if ($owner instanceof CustomRecordType) {
            return $owner->managers()->whereKey($user->getKey())->exists();
        }

        return false;
    }

    /**
     * Gestor só conta enquanto tem perfil GESTOR e está ativo.
     */
    private function isActiveManager(User $user): bool
    {
        return $user->user_role === UserRoleEnum::GESTOR && (bool) $user->is_active;
    }

    /**
     * Campo de valor que o usuário não pode ver neste dono.
     */
    public function isHiddenFor(?User $user, CustomField $field, ?Model $owner): bool
    {
        return $field->field_type === FieldTypeEnum::CURRENCY
            && ! $this->canView($user, $owner);
    }

    /**
     * Ids dos processos cujos valores o usuário pode ver.
     * `null` significa sem restrição (ADMIN); lista vazia, nenhum processo.
     *
     * @return array<string>|null
     */
    public function viewableEntityIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        if ($user->user_role === UserRoleEnum::ADMIN) {
            return null;
        }

        if (! $this->isActiveManager($user)) {
            return [];
        }

        return CustomEntity::query()
            ->where('manager_id', $user->id)
            ->orWhereHas('macroprocess', fn ($q) => $q->where('manager_id', $user->id))
            ->pluck('id')
            ->all();
    }
}
