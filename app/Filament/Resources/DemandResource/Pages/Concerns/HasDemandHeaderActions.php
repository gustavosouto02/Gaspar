<?php

namespace App\Filament\Resources\DemandResource\Pages\Concerns;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Enums\SatisfactionRatingEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\DemandTransitionException;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Models\ActivityLog;
use App\Models\Demand;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Notifications\DemandActivityNotification;
use App\Services\DemandTransitionService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait HasDemandHeaderActions
{
    /**
     * Botões de cabeçalho: fixos (excluir) + dinâmicos (transições de situação).
     */
    public function getHeaderActions(): array
    {
        $parentActions = parent::getHeaderActions() ?? [];
        $actions = [];

        $record = current(array_filter([
            $this->record ?? null,
            method_exists($this, 'getRecord') ? $this->getRecord() : null,
        ]));

        if (! $record) {
            return $parentActions;
        }

        $user = auth()->user();

        if ($record->requested_by === $user->id && empty($record->satisfaction_rating) && $record->processStatus?->isClosed()) {
            $actions[] = Actions\Action::make('rate_demand')
                ->label('Avaliar Atendimento')
                ->icon('heroicon-m-star')
                ->color('warning')
                ->form([
                    Select::make('satisfaction_rating')
                        ->label('Nota de Avaliação')
                        ->options(SatisfactionRatingEnum::options())
                        ->required(),
                    Textarea::make('satisfaction_comment')
                        ->label('Comentário')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record) {
                    $avaliadaStatus = ProcessStatus::findBySystemKey('evaluated');
                    $oldStatusId = $record->process_status_id;
                    $oldStatusName = $record->processStatus?->name ?? '—';
                    $newStatusName = $avaliadaStatus?->name ?? 'Avaliada';

                    $record->update([
                        'satisfaction_rating' => $data['satisfaction_rating'],
                        'satisfaction_comment' => $data['satisfaction_comment'],
                        'satisfaction_evaluated_at' => now(),
                        'process_status_id' => $avaliadaStatus ? $avaliadaStatus->id : $record->process_status_id,
                        'status' => DemandStatusEnum::EVALUATED,
                    ]);

                    ActivityLog::create([
                        'user_id' => auth()->id(),
                        'event' => 'transition',
                        'auditable_type' => Demand::class,
                        'auditable_id' => (string) $record->id,
                        'old_values' => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                        'new_values' => [
                            'process_status_id' => $avaliadaStatus?->id ?? $oldStatusId,
                            'status_name' => $newStatusName,
                            'action' => 'Avaliação de Atendimento',
                            'satisfaction_rating' => $data['satisfaction_rating'],
                        ],
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => now(),
                    ]);

                    Notification::make()->title('Avaliação enviada com sucesso!')->success()->send();
                    if (method_exists($this, 'refreshFormData')) {
                        $this->refreshFormData(['satisfaction_rating', 'satisfaction_comment', 'process_status_id']);
                    }
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                });
        }

        // Se a demanda já está cancelada ou avaliada, não possui mais transições de fluxo
        if ($record->processStatus?->isCanceled() || $record->processStatus?->isEvaluated()) {
            return $actions;
        }

        // Carrega as transições disponíveis a partir da situação atual
        $transitions = StatusTransition::with(['toStatus'])
            ->where('entity_id', $record->entity_id)
            ->where(function ($q) use ($record) {
                $q->whereNull('from_status_id')
                    ->orWhere('from_status_id', $record->process_status_id);
            })
            ->orderBy('display_order')
            ->get();

        foreach ($transitions as $transition) {
            // Cancelada e Avaliada não são opções de transição manual do fluxo
            if ($transition->toStatus?->isCanceled() || $transition->toStatus?->isEvaluated()) {
                continue;
            }

            if (! $transition->canBeTriggeredBy($user, $record)) {
                continue;
            }

            $color = $transition->toStatus?->color
                ? (ProcessStatusColorEnum::tryFrom($transition->toStatus->color)?->filamentColor() ?? 'primary')
                : 'primary';

            $label = $transition->label instanceof DemandButtonEnum
                ? $transition->label->value
                : ($transition->label ?? DemandButtonEnum::ENVIAR->value);

            $actions[] = Actions\Action::make('transition_'.Str::slug($transition->id))
                ->label($label)
                ->color($color)
                ->icon('heroicon-o-arrow-right-circle')
                ->requiresConfirmation()
                ->modalHeading("Confirmar: {$label}")
                ->modalDescription(function () use ($transition) {
                    $from = $transition->fromStatus?->name ?? 'Qualquer';
                    $to = $transition->toStatus?->name ?? '?';

                    return "Mover de \"{$from}\" para \"{$to}\"?";
                })
                ->modalSubmitActionLabel('Confirmar')
                ->action(function () use ($record, $transition, $user) {
                    // Na edição, o usuário pode ter digitado valores sem salvar:
                    // persiste o form antes, pois o serviço lê os valores do banco
                    if ($this instanceof EditDemand) {
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    }

                    try {
                        $newStatus = app(DemandTransitionService::class)
                            ->execute($record->refresh(), $transition, $user);
                    } catch (DemandTransitionException $e) {
                        Notification::make()
                            ->title('Não foi possível executar a transição')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title("Situação atualizada: {$newStatus->name}")
                        ->success()
                        ->send();

                    if (method_exists($this, 'refreshFormData')) {
                        $this->refreshFormData(['process_status_id']);
                    }

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                });
        }

        // --- BOTÃO DINÂMICO DE DEVOLVER / RETORNAR ---
        // Verifica qual foi a última situação desta demanda antes da atual
        $lastTransitionLog = ActivityLog::where('auditable_type', Demand::class)
            ->where('auditable_id', (string) $record->id)
            ->where('event', 'transition')
            ->orderByDesc('created_at')
            ->first();

        // Se encontrou histórico e a situação atual é o destino dessa última transição
        $isClosedOrEvaluated = $record->processStatus?->isClosed() || $record->processStatus?->isEvaluated();
        $isAssigneeOrAdmin = $user->isAdmin() || (string) $record->assigned_to === (string) $user->id;

        if (! $isClosedOrEvaluated && $isAssigneeOrAdmin && $lastTransitionLog && isset($lastTransitionLog->old_values['process_status_id']) && $record->process_status_id != $lastTransitionLog->old_values['process_status_id']) {
            $previousStatusId = $lastTransitionLog->old_values['process_status_id'];
            $previousStatusName = $lastTransitionLog->old_values['status_name'] ?? 'Situação Anterior';
            $previousUserId = $lastTransitionLog->user_id;

            // Verifica se a transição que trouxe a demanda até a situação atual permite retorno (allow_return)
            $transitionId = $lastTransitionLog->new_values['transition_id'] ?? null;
            if ($transitionId) {
                $transitionUsed = StatusTransition::find($transitionId);
            } else {
                $transitionUsed = StatusTransition::where('entity_id', $record->entity_id)
                    ->where(function ($q) use ($previousStatusId) {
                        $q->where('from_status_id', $previousStatusId)
                            ->orWhereNull('from_status_id');
                    })
                    ->where(function ($q) use ($record) {
                        $q->where('to_status_id', $record->process_status_id)
                            ->orWhere('default_to_status_id', $record->process_status_id)
                            ->orWhere(function ($condQ) use ($record) {
                                $condQ->whereHas('toStatus', fn ($s) => $s->where('system_key', 'conditional'))
                                    ->where(function ($matchQ) use ($record) {
                                        $matchQ->where('default_to_status_id', $record->process_status_id)
                                            ->orWhereHas('rules', fn ($r) => $r->where('to_status_id', $record->process_status_id));
                                    });
                            });
                    })
                    ->first();
            }

            $allowReturn = (bool) ($transitionUsed?->allow_return);

            if ($allowReturn) {
                $actions[] = Actions\Action::make('return_previous_status')
                    ->label("Retornar para: {$previousStatusName}")
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalHeading('Devolver Demanda')
                    ->modalDescription("Isso fará a demanda voltar para a situação \"{$previousStatusName}\" e será reatribuída ao responsável anterior.")
                    ->modalSubmitActionLabel('Confirmar Retorno')
                    ->action(function () use ($record, $user, $previousStatusId, $previousStatusName, $previousUserId) {
                        // Trava de subdemandas pendentes
                        if ($record->hasPendingChildren()) {
                            Notification::make()
                                ->title('Não é possível retornar a demanda')
                                ->body('Esta demanda possui subdemandas pendentes (ativas ou em rascunho). Conclua ou cancele-as primeiro.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $oldStatusId = $record->process_status_id;
                        $oldStatusName = $record->processStatus?->name ?? '—';

                        $record->process_status_id = $previousStatusId;

                        if ($previousUserId) {
                            $record->assigned_to = $previousUserId;
                        }
                        $record->save();

                        if (! $previousUserId) {
                            $record->autoAssign();
                        }

                        // Registra no audit log o retorno
                        ActivityLog::create([
                            'user_id' => $user->id,
                            'event' => 'transition',
                            'auditable_type' => Demand::class,
                            'auditable_id' => (string) $record->id,
                            'old_values' => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                            'new_values' => ['process_status_id' => $previousStatusId, 'status_name' => $previousStatusName, 'action' => 'Retorno Retroativo'],
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'created_at' => now(),
                        ]);

                        // Dispara notificações
                        try {
                            if ($record->assignee && $record->assignee->id !== $user->id) {
                                $record->assignee->notify(new DemandActivityNotification($record, "A demanda foi devolvida para: {$previousStatusName}"));
                            }
                            if ($record->requester && $record->requester->id !== $user->id) {
                                $record->requester->notify(new DemandActivityNotification($record, "A demanda foi devolvida para: {$previousStatusName}"));
                            }
                        } catch (\Throwable $e) {
                            Log::warning("Falha ao enviar notificação de devolução da demanda: {$e->getMessage()}");
                        }

                        Notification::make()->title("Demanda devolvida para: {$previousStatusName}")->success()->send();

                        if (method_exists($this, 'refreshFormData')) {
                            $this->refreshFormData(['process_status_id', 'assigned_to']);
                        }
                        $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                    });
            }
        }

        $actions[] = Actions\Action::make('cancelar')
            ->label('Cancelar Demanda')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancelar Demanda')
            ->modalDescription('Tem certeza que deseja cancelar esta demanda? Ela continuará salva, mas seu status será alterado para Cancelada.')
            ->action(function () use ($record) {
                // Trava de subdemandas pendentes
                if ($record->hasPendingChildren()) {
                    Notification::make()
                        ->title('Não é possível cancelar a demanda')
                        ->body('Esta demanda possui subdemandas pendentes (ativas ou em rascunho). Conclua ou cancele-as primeiro.')
                        ->danger()
                        ->send();

                    return;
                }

                $canceledStatus = \App\Models\ProcessStatus::findBySystemKey('canceled');
                $oldStatusId = $record->process_status_id;
                $oldStatusName = $record->processStatus?->name ?? '—';
                $newStatusName = $canceledStatus?->name ?? 'Cancelada';

                $record->update([
                    'status' => DemandStatusEnum::CANCELED->value,
                    'completed_at' => now(),
                    'process_status_id' => $canceledStatus ? $canceledStatus->id : $record->process_status_id,
                ]);

                \App\Models\ActivityLog::create([
                    'user_id'        => auth()->id(),
                    'event'          => 'transition',
                    'auditable_type' => \App\Models\Demand::class,
                    'auditable_id'   => (string) $record->id,
                    'old_values'     => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                    'new_values'     => [
                        'process_status_id'   => $canceledStatus?->id ?? $oldStatusId,
                        'status_name'         => $newStatusName,
                        'action'              => 'Cancelamento da Demanda',
                    ],
                    'ip_address'     => request()->ip(),
                    'user_agent'     => request()->userAgent(),
                    'created_at'     => now(),
                ]);

                Notification::make()->title('Demanda cancelada com sucesso!')->success()->send();
                $this->redirect(static::getResource()::getUrl('index'));
            })
            ->visible(fn () => 
                ! $record->processStatus?->isCanceled()
                && ! $record->processStatus?->isClosed()
                && ! $record->processStatus?->isEvaluated()
                && (auth()->user()->user_role === \App\Enums\UserRoleEnum::ADMIN
                    || $record->requested_by === auth()->id())
            );
        $actions[] = Actions\Action::make('export_pdf')
            ->label('Exportar PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->url(fn () => route('demands.pdf', ['record' => $record->id]))
            ->openUrlInNewTab();

        return array_merge($actions, $parentActions);
    }
}
