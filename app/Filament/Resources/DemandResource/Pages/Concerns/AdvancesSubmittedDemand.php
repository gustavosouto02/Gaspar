<?php

namespace App\Filament\Resources\DemandResource\Pages\Concerns;

use App\Enums\DemandStatusEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\Demand;
use App\Models\StatusTransition;
use App\Services\DemandTransitionService;
use Filament\Notifications\Notification;

/**
 * "Enviar para atendimento": a demanda sai de "Nova" pela transição do fluxo e passa
 * para o responsável da próxima situação. "Salvar" é o botão que mantém em rascunho.
 */
trait AdvancesSubmittedDemand
{
    /** A transição existe mas foi recusada: a demanda voltou para rascunho */
    private bool $submissionFailed = false;

    /**
     * Devolve true quando a situação avançou. Sem transição configurada a partir de
     * "Nova" (ou sem permissão para dispará-la), a demanda é apenas atribuída.
     */
    protected function advanceSubmittedDemand(Demand $demand): bool
    {
        $demand->refresh();
        $user = auth()->user();

        $transition = $demand->processStatus?->isNew()
            ? StatusTransition::where('entity_id', $demand->entity_id)
                ->where('from_status_id', $demand->process_status_id)
                ->orderBy('display_order')
                ->get()
                ->first(fn (StatusTransition $transition) => $transition->canBeTriggeredBy($user, $demand))
            : null;

        if (! $transition) {
            $demand->autoAssign();
            $demand->refresh();

            return false;
        }

        try {
            app(DemandTransitionService::class)->execute($demand, $transition, $user);
        } catch (DemandTransitionException $e) {
            $this->submissionFailed = true;
            $demand->updateQuietly(['status' => DemandStatusEnum::DRAFT]);

            Notification::make()
                ->title('A demanda foi salva como rascunho, mas não foi enviada')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        $demand->refresh();

        return true;
    }
}
