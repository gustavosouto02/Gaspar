<?php

namespace App\Services;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\ActivityLog;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use App\Notifications\DemandActivityNotification;
use App\Notifications\DemandSatisfactionNotification;
use Illuminate\Support\Facades\Log;

class DemandTransitionService
{
    public function __construct(
        private readonly TransitionDecisionEvaluator $decisionEvaluator,
        private readonly FieldPermissionResolver $permissionResolver,
    ) {}

    /**
     * Executa a transição de situação da demanda e devolve a situação final aplicada.
     *
     * @throws DemandTransitionException quando a transição é bloqueada por regra de negócio
     */
    public function execute(Demand $demand, StatusTransition $transition, User $user): ProcessStatus
    {
        $target = $transition->toStatus;

        if (! $target) {
            throw new DemandTransitionException('A situação de destino desta transição não existe mais.');
        }

        $currentStatus = $demand->processStatus;
        if ($currentStatus && ($currentStatus->isCanceled() || $currentStatus->isEvaluated())) {
            throw new DemandTransitionException("Não é possível realizar transições em uma demanda já finalizada ({$currentStatus->name}).");
        }

        // Gateway: a Condicional nunca recebe a demanda; a tabela de decisão
        // resolve a situação real antes de qualquer gravação
        $matchedRule = null;

        if ($target->isConditional()) {
            $decision = $this->decisionEvaluator->resolve($transition, $demand);

            $target = ProcessStatus::findOrFail($decision->toStatusId);
            $matchedRule = $decision->matchedRule;
        }

        if ($target->isEvaluated()) {
            throw new DemandTransitionException('A situação "Avaliada" é atribuída automaticamente após a avaliação do atendimento pelo demandante.');
        }

        if ($target->isCanceled()) {
            $canCancel = $user->user_role === \App\Enums\UserRoleEnum::ADMIN || $demand->requested_by === $user->id;
            if (! $canCancel) {
                throw new DemandTransitionException('Apenas o demandante ou um administrador podem cancelar esta demanda.');
            }
        }

        // Verifica se a transição vai encerrar a demanda e se pode ser concluída
        if ($target->isClosed() && ! $demand->canBeCompleted()) {
            throw new DemandTransitionException('Esta demanda possui subdemandas abertas. Conclua ou cancele-as primeiro.');
        }

        // Campos obrigatórios da situação ATUAL precisam estar preenchidos
        $this->assertRequiredFieldsFilled($demand, $user);

        $oldStatusId = $demand->process_status_id;
        $oldStatusName = $demand->processStatus?->name ?? '—';

        $discardedFields = $this->discardValuesFromLaterSteps($demand, $target);

        // Executa a transição
        $demand->process_status_id = $target->id;

        // Se estava em rascunho, ativa ao avançar no fluxo
        if ($demand->status === DemandStatusEnum::DRAFT) {
            $demand->status = DemandStatusEnum::ACTIVE;
        }

        // Registra o início do atendimento na primeira transição
        if (empty($demand->started_at)) {
            $demand->started_at = now();
        }

        // Se o prazo SLA ainda não foi calculado e o processo possui sla_hours
        if (empty($demand->sla_due_at)) {
            $entity = $demand->entity ?? CustomEntity::find($demand->entity_id);
            if ($entity && $entity->sla_hours) {
                $demand->sla_due_at = ($demand->started_at ?? now())->copy()->addHours($entity->sla_hours);
            }
        }

        if ($target->isClosed()) {
            $demand->status = DemandStatusEnum::CLOSED;
            $demand->completed_at = now();
        }
        $demand->save(); // save first so autoAssign sees new status
        $demand->autoAssign();

        // Registra no audit log
        ActivityLog::create([
            'user_id' => $user->id,
            'event' => 'transition',
            'auditable_type' => Demand::class,
            'auditable_id' => (string) $demand->id,
            'old_values' => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
            'new_values' => array_merge(
                [
                    'process_status_id' => $target->id,
                    'status_name' => $target->name,
                    'action' => $transition->label instanceof DemandButtonEnum ? $transition->label->value : $transition->label,
                    'transition_id' => $transition->id,
                ],
                $transition->toStatus?->isConditional()
                    ? ['via' => 'Condicional', 'rule_order' => $matchedRule?->rule_order]
                    : [],
                $discardedFields ? ['discarded_fields' => $discardedFields] : [],
            ),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        // Dispara notificações com proteção contra falha de SMTP
        try {
            if ($demand->assignee && $demand->assignee->id !== $user->id) {
                $demand->assignee->notify(new DemandActivityNotification($demand, "A situação foi alterada para: {$target->name}"));
            }
            if ($demand->requester && $demand->requester->id !== $user->id) {
                $demand->requester->notify(new DemandActivityNotification($demand, "A situação foi alterada para: {$target->name}"));
            }
            if ($target->isClosed() && $demand->requester) {
                $demand->requester->notify(new DemandSatisfactionNotification($demand));
            }
        } catch (\Throwable $e) {
            Log::warning("Falha ao enviar notificação de transição de demanda: {$e->getMessage()}");
        }

        return $target;
    }

    /**
     * Ao voltar para uma situação já percorrida, descarta os valores que ela
     * não enxerga (oculto para todos os papéis) e que foram gravados depois que
     * a demanda saiu dela, ou seja, decisões das etapas seguintes que estão
     * sendo refeitas (ex.: "Aprovação" ao retornar para Licitando). Valores
     * que já existiam quando a demanda saiu da situação são preservados.
     *
     * @return array<string> nomes dos campos descartados
     */
    public function discardValuesFromLaterSteps(Demand $demand, ProcessStatus $target): array
    {
        $entity = $demand->entity;
        if (! $entity) {
            return [];
        }

        $leftTargetAt = ActivityLog::where('auditable_type', Demand::class)
            ->where('auditable_id', (string) $demand->id)
            ->where('event', 'transition')
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (ActivityLog $log) => ($log->old_values['process_status_id'] ?? null) === $target->id)
            ?->created_at;

        // Situação ainda não percorrida: é um avanço, nada a descartar
        if (! $leftTargetAt) {
            return [];
        }

        $hiddenIds = array_keys(array_filter(
            $this->permissionResolver->possiblePermissions($entity, $target),
            fn (array $permissions) => $permissions === [FieldPermissionEnum::HIDDEN]
        ));

        if (empty($hiddenIds)) {
            return [];
        }

        $values = $demand->fieldValues()
            ->with('customField')
            ->whereIn('custom_field_id', $hiddenIds)
            ->where('created_at', '>=', $leftTargetAt)
            ->get();

        $values->each->delete();

        return $values->map(fn ($fieldValue) => $fieldValue->customField?->name)->filter()->values()->all();
    }

    /**
     * Bloqueia a transição se campos obrigatórios (pela matriz de permissões,
     * na situação atual e para o usuário) estiverem vazios em demand_field_values.
     *
     * @throws DemandTransitionException
     */
    private function assertRequiredFieldsFilled(Demand $demand, User $user): void
    {
        $currentStatus = $demand->processStatus;
        $entity = $demand->entity;

        if (! $currentStatus || ! $entity) {
            return;
        }

        $permissions = $this->permissionResolver->resolve($entity, $currentStatus, $user);

        $requiredIds = array_keys(array_filter(
            $permissions,
            fn (FieldPermissionEnum $permission) => $permission === FieldPermissionEnum::REQUIRED
        ));

        if (empty($requiredIds)) {
            return;
        }

        $filledIds = $demand->fieldValues()
            ->whereIn('custom_field_id', $requiredIds)
            ->get()
            ->filter(fn ($fieldValue) => trim((string) $fieldValue->value) !== '')
            ->pluck('custom_field_id')
            ->all();

        $missingIds = array_diff($requiredIds, $filledIds);

        if (empty($missingIds)) {
            return;
        }

        $names = CustomField::whereIn('id', $missingIds)->pluck('name')->implode(', ');

        throw new DemandTransitionException("Preencha os campos obrigatórios antes de mudar a situação: {$names}.");
    }
}
