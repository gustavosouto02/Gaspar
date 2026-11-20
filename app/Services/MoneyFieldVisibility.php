<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
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
     * Cadastros base guardam os campos em `custom_data` na própria tabela;
     * o dono do campo é o CustomRecordType do model (por `model_class`).
     */
    public function canViewForModel(?User $user, string $modelClass): bool
    {
        $type = CustomRecordType::where('model_class', $modelClass)
            ->where('is_system', true)
            ->first();

        return $this->canView($user, $type);
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
