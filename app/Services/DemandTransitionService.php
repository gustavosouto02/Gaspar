<?php

namespace App\Services;

use App\Exceptions\DemandTransitionException;
use App\Models\ActivityLog;
use App\Models\Demand;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use App\Notifications\DemandActivityNotification;
use App\Notifications\DemandSatisfactionNotification;

class DemandTransitionService
{
    public function __construct(
        private readonly TransitionDecisionEvaluator $decisionEvaluator,
    ) {
    }

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

        // Gateway: a Condicional nunca recebe a demanda; a tabela de decisão
        // resolve a situação real antes de qualquer gravação
        $matchedRule = null;

        if ($target->isConditional()) {
            $decision = $this->decisionEvaluator->resolve($transition, $demand);

            $target      = ProcessStatus::findOrFail($decision->toStatusId);
            $matchedRule = $decision->matchedRule;
        }

        // Verifica se a transição vai encerrar a demanda e se pode ser concluída
        if ($target->isClosed() && ! $demand->canBeCompleted()) {
            throw new DemandTransitionException('Esta demanda possui subdemandas abertas. Conclua ou cancele-as primeiro.');
        }

        $oldStatusId   = $demand->process_status_id;
        $oldStatusName = $demand->processStatus?->name ?? '—';

        // Executa a transição
        $demand->process_status_id = $target->id;
        $demand->save(); // save first so autoAssign sees new status
        $demand->autoAssign();

        // Registra no audit log
        ActivityLog::create([
            'user_id'        => $user->id,
            'event'          => 'transition',
            'auditable_type' => Demand::class,
            'auditable_id'   => (string) $demand->id,
            'old_values'     => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
            'new_values'     => array_merge(
                ['process_status_id' => $target->id, 'status_name' => $target->name, 'action' => $transition->label],
                $transition->toStatus?->isConditional()
                    ? ['via' => 'Condicional', 'rule_order' => $matchedRule?->rule_order]
                    : [],
            ),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'created_at'     => now(),
        ]);

        // Dispara notificações
        if ($demand->assignee && $demand->assignee->id !== $user->id) {
            $demand->assignee->notify(new DemandActivityNotification($demand, "A situação foi alterada para: {$target->name}"));
        }
        if ($demand->requester && $demand->requester->id !== $user->id) {
            $demand->requester->notify(new DemandActivityNotification($demand, "A situação foi alterada para: {$target->name}"));
        }
        if ($target->isClosed() && $demand->requester) {
            $demand->requester->notify(new DemandSatisfactionNotification($demand));
        }

        return $target;
    }
}
