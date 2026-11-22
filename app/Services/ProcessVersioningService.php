<?php

namespace App\Services;

use App\Enums\DemandStatusEnum;
use App\Exceptions\ProcessVersioningException;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\FieldPermission;
use App\Models\ProcessMember;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use Illuminate\Support\Facades\DB;

/**
 * Versionamento de processos por clone: cada versão é um CustomEntity próprio e
 * tudo que o processo define já é amarrado por entity_id. Por isso a demanda
 * criada numa versão só tramita nela, sem mudar Demand nem os serviços de transição.
 */
class ProcessVersioningService
{
    public const LOCKED_STRUCTURE_MESSAGE = 'Esta versão já tem demandas. Use "Editar estrutura" para criar uma nova versão.';

    public const INACTIVE_VERSION_MESSAGE = 'Esta é uma versão antiga do processo e está somente leitura.';

    public const DELETION_BLOCKED_MESSAGE = 'Não é possível excluir: esta versão do processo ainda tem demandas não encerradas (rascunhos contam como pendentes).';

    /** Situações em que a demanda é considerada encerrada para a trava de exclusão */
    private const CLOSED_STATUSES = [
        DemandStatusEnum::CLOSED,
        DemandStatusEnum::CANCELED,
        DemandStatusEnum::EVALUATED,
    ];

    /**
     * Clona a versão vigente em v(n+1), que passa a ser a vigente. A de origem
     * vira versão antiga (continua ativa, e suas demandas seguem tramitando nela).
     *
     * @throws ProcessVersioningException quando a origem não é a versão vigente
     */
    public function createNewVersion(CustomEntity $source): CustomEntity
    {
        return DB::transaction(function () use ($source) {
            // Relê a origem dentro da transação: evita duas versões nascendo da mesma vigente
            $source = CustomEntity::query()->lockForUpdate()->findOrFail($source->getKey());

            if (! $source->is_current) {
                throw new ProcessVersioningException('Só é possível criar uma nova versão a partir da versão vigente do processo.');
            }

            $rootId = $source->root_entity_id ?: $source->id;
            $nextVersion = (int) CustomEntity::withTrashed()->where('root_entity_id', $rootId)->max('version') + 1;

            // O código interno (índice único) é gerado normalmente; a interface exibe o da raiz
            $new = new CustomEntity($source->only([
                'name',
                'macroprocess_id',
                'description',
                'is_active',
                'purpose',
                'sla_hours',
                'manager_id',
            ]));
            $new->created_by = auth()->id() ?? $source->created_by;
            $new->forceFill([
                'root_entity_id' => $rootId,
                'version' => max($nextVersion, 2),
                'is_current' => true,
                'previous_version_id' => $source->id,
            ]);
            $new->save();

            // O hook created anexa as situações de sistema ao novo processo: limpa antes de copiar
            $new->processStatuses()->detach();

            $this->copyStructure($source, $new);

            $source->forceFill(['is_current' => false])->save();

            return $new->refresh();
        });
    }

    /** Há demandas (em qualquer estado) nesta versão? */
    public function hasDemands(CustomEntity $entity): bool
    {
        return Demand::where('entity_id', $entity->id)->exists();
    }

    /** Há demandas não encerradas nesta versão? (rascunho conta como pendente) */
    public function hasPendingDemands(CustomEntity $entity): bool
    {
        return Demand::where('entity_id', $entity->id)
            ->whereNotIn('status', array_map(fn (DemandStatusEnum $status) => $status->value, self::CLOSED_STATUSES))
            ->exists();
    }

    /** A estrutura (campos, situações, transições, papéis, permissões) está travada? */
    public function isStructureLocked(CustomEntity $entity): bool
    {
        return ! $entity->is_current || $this->hasDemands($entity);
    }

    /** Aviso exibido nas telas de estrutura travada */
    public function lockedMessage(CustomEntity $entity): ?string
    {
        if (! $entity->is_current) {
            return self::INACTIVE_VERSION_MESSAGE;
        }

        return $this->hasDemands($entity) ? self::LOCKED_STRUCTURE_MESSAGE : null;
    }

    private function copyStructure(CustomEntity $source, CustomEntity $new): void
    {
        foreach ($source->fields()->get() as $field) {
            $new->fields()->attach($field->id, ['field_order' => $field->pivot->field_order]);
        }

        foreach ($source->processStatuses()->get() as $status) {
            $new->processStatuses()->attach($status->id, ['display_order' => $status->pivot->display_order]);
        }

        // Situações, campos, papéis e usuários são cadastros globais (mesmos ids);
        // só as transições ganham novos ids, e as regras precisam apontar para elas
        $transitionIds = [];
        foreach (StatusTransition::where('entity_id', $source->id)->get() as $transition) {
            $copy = $transition->replicate();
            $copy->entity_id = $new->id;
            $copy->saveQuietly();

            $transitionIds[$transition->id] = $copy->id;
        }

        foreach (TransitionRule::whereIn('status_transition_id', array_keys($transitionIds))->get() as $rule) {
            $copy = $rule->replicate();
            $copy->status_transition_id = $transitionIds[$rule->status_transition_id];
            $copy->saveQuietly();
        }

        foreach (FieldPermission::where('entity_id', $source->id)->get() as $permission) {
            $copy = $permission->replicate();
            $copy->entity_id = $new->id;
            $copy->saveQuietly();
        }

        foreach (ProcessMember::where('entity_id', $source->id)->get() as $member) {
            $copy = $member->replicate();
            $copy->entity_id = $new->id;
            $copy->saveQuietly();
        }
    }
}
