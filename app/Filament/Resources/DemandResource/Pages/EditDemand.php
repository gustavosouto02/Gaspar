<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Filament\Resources\DemandResource;
use App\Filament\Resources\DemandResource\Pages\Concerns\HasDemandHeaderActions;
use App\Filament\Resources\DemandResource\Widgets\DemandEvaluationWidget;
use App\Models\CustomEntity;
use App\Models\DemandComment;
use App\Models\DemandFieldValue;
use App\Notifications\DemandActivityNotification;
use App\Services\FieldPermissionResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditDemand extends EditRecord
{
    use HasDemandHeaderActions;

    protected static string $resource = DemandResource::class;

    public string $new_treatment = '';

    public function addTreatment()
    {
        if (empty(trim($this->new_treatment))) {
            return;
        }

        DemandComment::create([
            'demand_id' => $this->record->id,
            'user_id' => auth()->id(),
            'comment' => $this->new_treatment,
        ]);

        $this->new_treatment = '';

        Notification::make()
            ->title('Tratamento adicionado com sucesso!')
            ->success()
            ->send();
    }

    protected static string $view = 'filament.resources.demand-resource.pages.edit-demand';

    /**
     * Ao carregar o form para edição, injeta os valores salvos em
     * demand_field_values de volta no namespace field_data.{key}.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $fieldValues = $this->getRecord()
            ->fieldValues()
            ->with('customField')
            ->get();

        foreach ($fieldValues as $fv) {
            if ($fv->customField) {
                $data['field_data'][$fv->customField->key] = $fv->value;
            }
        }

        return $data;
    }

    private array $fieldData = [];

    private ?string $newTreatment = null;

    public bool $isSubmittingDraft = false;

    /**
     * Salvar alterações de um rascunho ignora a obrigatoriedade dos campos
     * dinâmicos; "Enviar para atendimento" valida normalmente
     * (lido pela closure de required() em buildDynamicFieldSchema)
     */
    public function isSavingDraft(): bool
    {
        return $this->record->status === DemandStatusEnum::DRAFT && ! $this->isSubmittingDraft;
    }

    /**
     * Antes de salvar, extrai field_data e new_treatment.
     * Se estiver enviando um rascunho para atendimento, atualiza status e SLA.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        $this->newTreatment = $data['new_treatment'] ?? null;
        unset($data['new_treatment']);

        if ($this->isSubmittingDraft) {
            $data['status'] = DemandStatusEnum::ACTIVE->value;

            $entityId = $data['entity_id'] ?? $this->record->entity_id;
            if (! empty($entityId) && empty($this->record->sla_due_at)) {
                $entity = CustomEntity::find($entityId);
                if ($entity && $entity->sla_hours) {
                    $data['sla_due_at'] = now()->addHours($entity->sla_hours);
                }
            }
        }

        return $data;
    }

    /**
     * Após salvar, atualiza demand_field_values, comentários, autoAssign, etc.
     */
    protected function afterSave(): void
    {
        $this->saveFieldValues(
            $this->getRecord()->id,
            $this->getRecord()->entity_id
        );

        if ($this->newTreatment) {
            DemandComment::create([
                'demand_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'comment' => $this->newTreatment,
            ]);

            $this->form->fill(['new_treatment' => null] + $this->form->getState());
        }

        if ($this->isSubmittingDraft) {
            $record = $this->getRecord();
            $record->autoAssign();
            $record->refresh();

            try {
                if ($record->assignee && $record->assignee->id !== auth()->id()) {
                    $record->assignee->notify(new DemandActivityNotification($record, 'Uma nova demanda foi enviada e atribuída a você.'));
                }

                if ($record->requester) {
                    $record->requester->notify(new DemandActivityNotification($record, 'Sua demanda foi enviada para atendimento com sucesso.'));
                }
            } catch (\Throwable $e) {
                Log::warning("Falha ao enviar notificação de envio de demanda: {$e->getMessage()}");
            }
        }

        // Se a pesquisa de satisfação foi respondida e a demanda está encerrada, avalia automaticamente
        $record = $this->getRecord()->fresh();
        if (
            $record->satisfaction_rating
            && $record->status === DemandStatusEnum::CLOSED
        ) {
            $record->updateQuietly([
                'status' => DemandStatusEnum::EVALUATED,
            ]);

            Notification::make()
                ->title('Obrigado pela sua avaliação!')
                ->body('A demanda foi marcada como Avaliada.')
                ->success()
                ->send();
        }
    }

    public function submitDraft(): void
    {
        $this->isSubmittingDraft = true;
        $this->save();
    }

    protected function getFormActions(): array
    {
        $actions = [];

        // Se for um rascunho, adiciona o botão principal "Enviar para atendimento"
        if ($this->record->status === DemandStatusEnum::DRAFT) {
            $actions[] = Action::make('enviar_para_atendimento')
                ->label('Enviar para atendimento')
                ->color('primary')
                ->action(function () {
                    $this->submitDraft();

                    Notification::make()
                        ->title('Demanda enviada para atendimento!')
                        ->success()
                        ->send();

                    // Redireciona para atualizar a tela e botões
                    return redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                });
        }

        // Botão padrão de salvar alterações
        $actions[] = $this->getSaveFormAction()->label('Salvar Alterações');

        $actions[] = $this->getCancelFormAction();

        return $actions;
    }

    private function saveFieldValues(string $demandId, string $entityId): void
    {
        if (empty($this->fieldData)) {
            return;
        }

        $entity = CustomEntity::find($entityId);
        if (! $entity) {
            return;
        }
        $fields = $entity->fields()->get()->keyBy('key');

        $blockedFieldIds = $this->blockedFieldIds($entity);

        foreach ($this->fieldData as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue;
            }

            // Garantia no servidor: campos somente leitura/ocultos para o
            // usuário são descartados mesmo que venham no payload
            if (in_array($field->id, $blockedFieldIds, true)) {
                continue;
            }

            DemandFieldValue::updateOrCreate(
                ['demand_id' => $demandId, 'custom_field_id' => $field->id],
                ['value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }

        // Remove valores de campos que não existem mais no processo
        // (a lista completa preserva os valores de campos bloqueados)
        $validFieldIds = $fields->pluck('id')->all();
        DemandFieldValue::where('demand_id', $demandId)
            ->whereNotIn('custom_field_id', $validFieldIds)
            ->delete();
    }

    /**
     * Ids dos campos READONLY/HIDDEN para o usuário na situação atual da demanda
     *
     * @return array<string>
     */
    private function blockedFieldIds(CustomEntity $entity): array
    {
        $status = $this->getRecord()->processStatus;
        $user = auth()->user();

        if (! $status || ! $user) {
            return [];
        }

        $permissions = app(FieldPermissionResolver::class)->resolve($entity, $status, $user);

        return array_keys(array_filter(
            $permissions,
            fn (FieldPermissionEnum $permission) => in_array($permission, [
                FieldPermissionEnum::READONLY,
                FieldPermissionEnum::HIDDEN,
            ], true)
        ));
    }

    protected function getFooterWidgets(): array
    {
        return [
            DemandEvaluationWidget::class,
        ];
    }
}
