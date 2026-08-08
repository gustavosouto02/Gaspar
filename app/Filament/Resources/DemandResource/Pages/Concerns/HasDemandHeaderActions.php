<?php

namespace App\Filament\Resources\DemandResource\Pages\Concerns;

use App\Enums\ProcessStatusColorEnum;
use App\Models\ActivityLog;
use App\Models\StatusTransition;
use Filament\Actions;
use Filament\Notifications\Notification;
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
        
        $user   = auth()->user();

        if ($record->requested_by === $user->id && empty($record->satisfaction_rating) && $record->processStatus?->name === 'Encerrada') {
            $actions[] = Actions\Action::make('rate_demand')
                ->label('Avaliar Atendimento')
                ->icon('heroicon-m-star')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('satisfaction_rating')
                        ->label('Nota de Avaliação')
                        ->options(\App\Enums\SatisfactionRatingEnum::options())
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('satisfaction_comment')
                        ->label('Comentário')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record) {
                    $avaliadaStatus = \App\Models\ProcessStatus::where('name', 'Avaliada')->first();
                    
                    $record->update([
                        'satisfaction_rating' => $data['satisfaction_rating'],
                        'satisfaction_comment' => $data['satisfaction_comment'],
                        'satisfaction_evaluated_at' => now(),
                        'process_status_id' => $avaliadaStatus ? $avaliadaStatus->id : $record->process_status_id,
                    ]);
                    Notification::make()->title('Avaliação enviada com sucesso!')->success()->send();
                    if (method_exists($this, 'refreshFormData')) {
                        $this->refreshFormData(['satisfaction_rating', 'satisfaction_comment', 'process_status_id']);
                    }
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                });
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
            if (! $transition->canBeTriggeredBy($user, $record)) {
                continue;
            }

            $color = $transition->toStatus?->color
                ? (ProcessStatusColorEnum::tryFrom($transition->toStatus->color)?->filamentColor() ?? 'primary')
                : 'primary';

            $label = $transition->toStatus?->name === 'Encerrada' ? 'Encerrar demanda' : $transition->label;

            $actions[] = Actions\Action::make('transition_' . Str::slug($transition->id))
                ->label($label)
                ->color($color)
                ->icon('heroicon-o-arrow-right-circle')
                ->requiresConfirmation()
                ->modalHeading("Confirmar: {$label}")
                ->modalDescription(function () use ($transition) {
                    $from = $transition->fromStatus?->name ?? 'Qualquer';
                    $to   = $transition->toStatus?->name ?? '?';
                    return "Mover de \"{$from}\" para \"{$to}\"?";
                })
                ->modalSubmitActionLabel('Confirmar')
                ->action(function () use ($record, $transition, $user) {
                    // Verifica se a transição vai encerrar a demanda e se pode ser concluída
                    if ($transition->toStatus?->name === 'Encerrada' && ! $record->canBeCompleted()) {
                        Notification::make()
                            ->title('Não é possível encerrar')
                            ->body('Esta demanda possui subdemandas abertas. Conclua ou cancele-as primeiro.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $oldStatusId   = $record->process_status_id;
                    $oldStatusName = $record->processStatus?->name ?? '—';

                    // Executa a transição
                    $record->process_status_id = $transition->to_status_id;
                    $record->save(); // save first so autoAssign sees new status
                    $record->autoAssign();

                    // Registra no audit log
                    ActivityLog::create([
                        'user_id'        => $user->id,
                        'event'          => 'transition',
                        'auditable_type' => \App\Models\Demand::class,
                        'auditable_id'   => (string) $record->id,
                        'old_values'     => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                        'new_values'     => ['process_status_id' => $transition->to_status_id, 'status_name' => $transition->toStatus?->name, 'action' => $transition->label],
                        'ip_address'     => request()->ip(),
                        'user_agent'     => request()->userAgent(),
                        'created_at'     => now(),
                    ]);

                    Notification::make()
                        ->title("Situação atualizada: {$transition->toStatus?->name}")
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
        $lastTransitionLog = ActivityLog::where('auditable_type', \App\Models\Demand::class)
            ->where('auditable_id', (string) $record->id)
            ->where('event', 'transition')
            ->orderByDesc('created_at')
            ->first();

        // Se encontrou histórico e a situação atual é o destino dessa última transição
        $isClosedOrEvaluated = in_array($record->processStatus?->name, ['Encerrada', 'Avaliada']);
        
        if (! $isClosedOrEvaluated && $lastTransitionLog && isset($lastTransitionLog->old_values['process_status_id']) && $record->process_status_id != $lastTransitionLog->old_values['process_status_id']) {
            $previousStatusId = $lastTransitionLog->old_values['process_status_id'];
            $previousStatusName = $lastTransitionLog->old_values['status_name'] ?? 'Situação Anterior';
            $previousUserId = $lastTransitionLog->user_id;

            $actions[] = Actions\Action::make('return_previous_status')
                ->label("Retornar para: {$previousStatusName}")
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->requiresConfirmation()
                ->modalHeading("Devolver Demanda")
                ->modalDescription("Isso fará a demanda voltar para a situação \"{$previousStatusName}\" e será reatribuída ao responsável anterior.")
                ->modalSubmitActionLabel('Confirmar Retorno')
                ->action(function () use ($record, $user, $previousStatusId, $previousStatusName, $previousUserId) {
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
                        'user_id'        => $user->id,
                        'event'          => 'transition',
                        'auditable_type' => \App\Models\Demand::class,
                        'auditable_id'   => (string) $record->id,
                        'old_values'     => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                        'new_values'     => ['process_status_id' => $previousStatusId, 'status_name' => $previousStatusName, 'action' => 'Retorno Retroativo'],
                        'ip_address'     => request()->ip(),
                        'user_agent'     => request()->userAgent(),
                        'created_at'     => now(),
                    ]);

                    Notification::make()->title("Demanda devolvida para: {$previousStatusName}")->success()->send();

                    if (method_exists($this, 'refreshFormData')) {
                        $this->refreshFormData(['process_status_id', 'assigned_to']);
                    }
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                });
        }

        $actions[] = Actions\DeleteAction::make();

        return array_merge($actions, $parentActions);
    }
}
