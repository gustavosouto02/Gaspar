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
    public const LOCKED_STRUCTURE_MESSAGE = 'Esta versão já tem demandas. Use o botão "Criar nova versão" para alterar a estrutura.';

    public const INACTIVE_VERSION_MESSAGE = 'Esta é uma versão antiga do processo e está somente leitura.';

    public const DELETION_BLOCKED_MESSAGE = 'Não é possível excluir: esta versão do processo ainda tem demandas não encerradas (rascunhos contam como pendentes).';

    public const DRAFT_EXISTS_MESSAGE = 'Este processo já tem uma versão em rascunho. Publique ou exclua o rascunho antes de criar outra.';

    public const NOT_A_DRAFT_MESSAGE = 'Só é possível publicar uma versão em rascunho.';

    /** Situações em que a demanda é considerada encerrada para a trava de exclusão */
    private const CLOSED_STATUSES = [
        DemandStatusEnum::CLOSED,
        DemandStatusEnum::CANCELED,
        DemandStatusEnum::EVALUATED,
    ];

    /**
     * Clona a versão vigente em v(n+1) como RASCUNHO. A vigente continua valendo e
     * recebendo demandas; a troca só acontece em publishVersion(). O rascunho herda
     * os metadados (inclusive o gestor) e pode ser alterado à vontade antes de publicar.
     *
     * @throws ProcessVersioningException quando a origem não é a vigente ou já há rascunho
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

            if ($this->draftFor($rootId)) {
                throw new ProcessVersioningException(self::DRAFT_EXISTS_MESSAGE);
            }

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
                'version' => $this->nextVersionNumber($source),
                'is_current' => false,
                'is_draft' => true,
                'previous_version_id' => $source->id,
            ]);
            $new->save();

            // O hook created anexa as situações de sistema ao novo processo: limpa antes de copiar
            $new->processStatuses()->detach();

            $this->copyStructure($source, $new);

            return $new->refresh();
        });
    }

    /**
     * Publica o rascunho: ele vira a versão vigente e a anterior passa a ser antiga
     * (intacta, só deixa de receber novas demandas; as em aberto seguem nela).
     *
     * @throws ProcessVersioningException quando a versão não é um rascunho
     */
    public function publishVersion(CustomEntity $draft): CustomEntity
    {
        return DB::transaction(function () use ($draft) {
            $draft = CustomEntity::query()->lockForUpdate()->findOrFail($draft->getKey());

            if (! $draft->is_draft) {
                throw new ProcessVersioningException(self::NOT_A_DRAFT_MESSAGE);
            }

            $rootId = $draft->root_entity_id ?: $draft->id;

            // Trava todas as versões da raiz: duas publicações simultâneas não deixam duas vigentes
            CustomEntity::query()->where('root_entity_id', $rootId)->lockForUpdate()->get();

            CustomEntity::query()
                ->where('root_entity_id', $rootId)
                ->where('is_current', true)
                ->where('id', '!=', $draft->id)
                ->get()
                ->each(fn (CustomEntity $old) => $old->forceFill(['is_current' => false])->saveQuietly());

            $draft->forceFill(['is_draft' => false, 'is_current' => true])->saveQuietly();

            return $draft->refresh();
        });
    }

    /** Rascunho em aberto do processo (pela raiz), se houver */
    public function draftFor(string $rootId): ?CustomEntity
    {
        return CustomEntity::query()->where('root_entity_id', $rootId)->where('is_draft', true)->first();
    }

    /**
     * Número da próxima versão do processo: maior versão já criada (inclusive as
     * excluídas) + 1. É o número que createNewVersion() vai gravar.
     */
    public function nextVersionNumber(CustomEntity $entity): int
    {
        $rootId = $entity->root_entity_id ?: $entity->id;

        return max((int) CustomEntity::withTrashed()->where('root_entity_id', $rootId)->max('version') + 1, 2);
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

    /** Versão antiga: já publicada e substituída (rascunho não conta) */
    public function isInactiveVersion(CustomEntity $entity): bool
    {
        return ! $entity->is_current && ! $entity->is_draft;
    }

    /** A estrutura (campos, situações, transições, papéis, permissões) está travada? */
    public function isStructureLocked(CustomEntity $entity): bool
    {
        return $this->isInactiveVersion($entity) || $this->hasDemands($entity);
    }

    /** Aviso exibido nas telas de estrutura travada */
    public function lockedMessage(CustomEntity $entity): ?string
    {
        if ($this->isInactiveVersion($entity)) {
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
