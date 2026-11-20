<?php

namespace App\Services;

use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\FieldPermission;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Regra de visibilidade dos campos de valor (FieldTypeEnum::CURRENCY).
 *
 * Quem pode ver um valor depende do "dono" do campo:
 * - ADMIN vê sempre;
 * - Processo (CustomEntity): o gestor do processo ou o gestor do macroprocesso
 *   ao qual ele pertence;
 * - Cadastro (CustomRecordType): qualquer um dos gestores do cadastro;
 * - os gestores precisam continuar com perfil GESTOR e ativos (quem virou
 *   EXECUTOR ou foi desativado deixa de ver, mesmo ainda constando como gestor);
 * - qualquer outro caso (inclusive sem usuário autenticado): ninguém.
 *
 * Exceção por demanda: se o admin configurou acesso ao campo na matriz de
 * permissões (linha explícita da situação diferente de Oculto, do papel da
 * pessoa ou genérica), o campo é liberado nas telas da demanda naquela situação
 * (formulário, salvar, preenchimento e tramitação). Relatório, PDF e busca
 * não dependem da situação e continuam restritos a ADMIN e gestores.
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
     * Campo de valor que o usuário não pode ver na situação da demanda: além da
     * regra do dono, vale a liberação por linha explícita da matriz de permissões.
     */
    public function isHiddenInDemand(?User $user, CustomField $field, ?CustomEntity $entity, ?ProcessStatus $status): bool
    {
        return $this->isHiddenFor($user, $field, $entity)
            && ! $this->isReleasedByMatrix($user, $field, $entity, $status);
    }

    /**
     * O admin liberou o campo de valor para esta pessoa nesta situação: existe
     * linha explícita na matriz (do papel dela no processo ou genérica) cuja
     * permissão efetiva não é Oculto. A ausência de linha (fallback Somente
     * leitura) não libera.
     */
    public function isReleasedByMatrix(?User $user, CustomField $field, ?CustomEntity $entity, ?ProcessStatus $status): bool
    {
        if (! $user || ! $entity || ! $status || $field->field_type !== FieldTypeEnum::CURRENCY) {
            return false;
        }

        $roleIds = ProcessMember::where('entity_id', $entity->id)
            ->where('user_id', $user->id)
            ->pluck('process_role_id')
            ->all();

        $rows = FieldPermission::where('entity_id', $entity->id)
            ->where('process_status_id', $status->id)
            ->where('custom_field_id', $field->id)
            ->where(function ($q) use ($roleIds) {
                $q->whereNull('process_role_id')
                    ->orWhereIn('process_role_id', $roleIds);
            })
            ->get();

        return $this->rowsRelease($rows);
    }

    /**
     * Linhas explícitas da matriz (já filtradas para o campo, a situação e a
     * pessoa) que liberam o campo. Como na matriz, a linha do papel vence a
     * genérica e, entre vários papéis, vale a mais permissiva.
     *
     * @param  Collection<int, FieldPermission>  $rows
     */
    public function rowsRelease(Collection $rows): bool
    {
        $roleRows = $rows->whereNotNull('process_role_id');

        $effective = $roleRows->isNotEmpty()
            ? $roleRows->map(fn (FieldPermission $row) => $row->permission)
                ->sortByDesc(fn (FieldPermissionEnum $p) => $p->rank())
                ->first()
            : $rows->whereNull('process_role_id')->first()?->permission;

        return $effective !== null && $effective !== FieldPermissionEnum::HIDDEN;
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

    /**
     * Garantia no servidor para cadastros (JSON regravado por inteiro): quando
     * o usuário autenticado não pode ver os valores, os campos de valor voltam
     * ao que estava gravado, ignorando o que veio no payload.
     * Sem usuário autenticado (seeders, console) nada é alterado.
     *
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $original
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    public function preserveHiddenValues(?Model $owner, array $incoming, array $original, Collection $fields): array
    {
        $user = auth()->user();

        if (! $user || $this->canView($user, $owner)) {
            return $incoming;
        }

        foreach ($fields as $field) {
            if ($field->field_type !== FieldTypeEnum::CURRENCY) {
                continue;
            }

            if (array_key_exists($field->key, $original)) {
                $incoming[$field->key] = $original[$field->key];
            } else {
                unset($incoming[$field->key]);
            }
        }

        return $incoming;
    }

    /**
     * O Filament preenche o estado do Livewire com o registro inteiro, inclusive
     * chaves sem componente no formulário; sem isto o valor chegaria ao navegador
     * (e ao payload da requisição) mesmo com o campo omitido da tela.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function stripHiddenFromFormData(array $data, Model $record): array
    {
        if ($record instanceof CustomRecord) {
            $column = 'data_json';
            $type = $record->recordType;
        } elseif (method_exists($record, 'getCustomRecordType')) {
            $column = 'custom_data';
            $type = $record->getCustomRecordType();
        } else {
            return $data;
        }

        if (! $type || ! isset($data[$column]) || ! is_array($data[$column])) {
            return $data;
        }

        $user = auth()->user();

        foreach ($type->fields as $field) {
            if ($this->isHiddenFor($user, $field, $type)) {
                unset($data[$column][$field->key]);
            }
        }

        return $data;
    }
}
