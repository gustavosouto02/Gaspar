<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Enums\ProcessStatusColorEnum;
use App\Filament\Resources\DemandResource;
use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\DemandFieldValue;
use App\Models\StatusTransition;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

use App\Filament\Resources\DemandResource\Pages\Concerns\HasDemandHeaderActions;

class EditDemand extends EditRecord
{
    use HasDemandHeaderActions;

    protected static string $resource = DemandResource::class;

    public string $new_treatment = '';

    public function addTreatment()
    {
        if (empty(trim($this->new_treatment))) return;

        \App\Models\DemandComment::create([
            'demand_id' => $this->record->id,
            'user_id' => auth()->id(),
            'comment' => $this->new_treatment,
        ]);

        $this->new_treatment = '';

        \Filament\Notifications\Notification::make()
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
            $data['status'] = \App\Enums\DemandStatusEnum::ACTIVE->value;

            if (! empty($data['entity_id']) && empty($this->record->sla_due_at)) {
                $entity = \App\Models\CustomEntity::find($data['entity_id']);
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
            \App\Models\DemandComment::create([
                'demand_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'content' => $this->newTreatment,
            ]);
            
            $this->form->fill(['new_treatment' => null] + $this->form->getState());
        }

        if ($this->isSubmittingDraft) {
            $this->getRecord()->autoAssign();
        }

        // Se a pesquisa de satisfação foi respondida e a demanda está encerrada, avalia automaticamente
        $record = $this->getRecord()->fresh();
        if (
            $record->satisfaction_rating
            && $record->status === \App\Enums\DemandStatusEnum::CLOSED
        ) {
            $record->updateQuietly([
                'status' => \App\Enums\DemandStatusEnum::EVALUATED,
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Obrigado pela sua avaliação!')
                ->body('A demanda foi marcada como Avaliada.')
                ->success()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        $actions = [];

        // Se for um rascunho, adiciona o botão principal "Enviar para atendimento"
        if ($this->record->status === \App\Enums\DemandStatusEnum::DRAFT) {
            $actions[] = \Filament\Actions\Action::make('enviar_para_atendimento')
                ->label('Enviar para atendimento')
                ->color('primary')
                ->action(function () {
                    $this->isSubmittingDraft = true;
                    $this->save();
                    
                    \Filament\Notifications\Notification::make()
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

        $entity = \App\Models\CustomEntity::find($entityId);
        if (! $entity) return;
        $fields = $entity->fields()->get()->keyBy('key');

        foreach ($this->fieldData as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue;
            }

            DemandFieldValue::updateOrCreate(
                ['demand_id' => $demandId, 'custom_field_id' => $field->id],
                ['value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }

        // Remove valores de campos que não existem mais no processo
        $validFieldIds = $fields->pluck('id')->all();
        DemandFieldValue::where('demand_id', $demandId)
            ->whereNotIn('custom_field_id', $validFieldIds)
            ->delete();
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Resources\DemandResource\Widgets\DemandEvaluationWidget::class,
        ];
    }
}
