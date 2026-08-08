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
    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $user   = auth()->user();

        $actions = [
            Actions\DeleteAction::make(),
        ];

        if (! $record->entity_id) {
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
                    $record->update(['process_status_id' => $transition->to_status_id]);
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

                    // No ViewRecord/EditRecord, refresh
                    if (method_exists($this, 'refreshFormData')) {
                        $this->refreshFormData(['process_status_id']);
                    }
                    
                    // Force refresh para ViewRecord se necessário, ou redireciona
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                });
        }

        return $actions;
    }
}
