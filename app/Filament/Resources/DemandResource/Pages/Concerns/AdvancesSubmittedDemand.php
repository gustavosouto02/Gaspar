<?php

namespace App\Filament\Resources\DemandResource\Pages\Concerns;

use App\Enums\DemandStatusEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\Demand;
use App\Models\StatusTransition;
use App\Notifications\DemandActivityNotification;
use App\Services\DemandTransitionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Enviar para atendimento": a demanda sai de "Nova" pela transição do fluxo e passa
 * para o responsável da próxima situação. "Salvar" é o botão que mantém em rascunho.
 */
trait AdvancesSubmittedDemand
{
    /** O envio foi recusado ou falhou: a demanda voltou para rascunho */
    private bool $submissionFailed = false;

    /**
     * Criar/salvar roda dentro de uma transação: uma falha no meio não deixa demanda
     * pela metade (nem duplicada, ao clicar de novo).
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    /**
     * Devolve true quando a situação avançou. Sem transição configurada a partir de
     * "Nova" (ou sem permissão para dispará-la), a demanda é apenas atribuída.
     */
    protected function advanceSubmittedDemand(Demand $demand): bool
    {
        $demand->refresh();
        $user = auth()->user();

        try {
            // Transação própria: a falha desfaz só o envio, e o preenchimento continua salvo
            return DB::transaction(function () use ($demand, $user) {
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

                app(DemandTransitionService::class)->execute($demand, $transition, $user);
                $demand->refresh();

                return true;
            });
        } catch (DemandTransitionException $e) {
            $this->revertToDraft($demand, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->revertToDraft($demand, 'Ocorreu um erro inesperado durante o envio. Tente novamente e, se o problema continuar, avise o administrador do sistema.');
        }

        return false;
    }

    private function revertToDraft(Demand $demand, string $reason): void
    {
        $this->submissionFailed = true;

        // Descarta o que ficou só em memória; rascunho não tem prazo (o SLA corre a partir do envio)
        $demand->refresh();
        $demand->updateQuietly(['status' => DemandStatusEnum::DRAFT, 'sla_due_at' => null]);

        Notification::make()
            ->title('A demanda foi salva como rascunho, mas não foi enviada')
            ->body($reason)
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * E-mails do envio, só depois do commit. Quando a situação avança, o serviço de transição
     * já avisa o responsável e o demandante (se ele não for quem enviou).
     */
    protected function notifySubmission(Demand $demand, bool $advanced, string $assigneeMessage, string $requesterMessage): void
    {
        DB::afterCommit(function () use ($demand, $advanced, $assigneeMessage, $requesterMessage) {
            try {
                $assignee = $demand->assignee;
                $requester = $demand->requester;

                if (! $advanced && $assignee && $assignee->id !== auth()->id() && $assignee->id !== $requester?->id) {
                    $assignee->notify(new DemandActivityNotification($demand, $assigneeMessage));
                }

                if ($requester && (! $advanced || $requester->id === auth()->id())) {
                    $requester->notify(new DemandActivityNotification($demand, $requesterMessage));
                }
            } catch (\Throwable $e) {
                Log::warning("Falha ao enviar notificação de envio de demanda: {$e->getMessage()}");
            }
        });
    }
}
