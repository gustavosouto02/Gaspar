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

    /**
     * Antes de salvar, extrai field_data e new_treatment (não são colunas reais em demands).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        $this->newTreatment = $data['new_treatment'] ?? null;
        unset($data['new_treatment']);

        return $data;
    }

    /**
     * Após salvar, atualiza demand_field_values e verifica pesquisa de satisfação.
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
            
            // Clear the form field so it doesn't stay populated on next render
            $this->form->fill(['new_treatment' => null] + $this->form->getState());
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
        $actions = parent::getFormActions();
        
        foreach ($actions as $action) {
            $action->extraAttributes(
                array_merge($action->getExtraAttributes(), ['form' => 'form'])
            );
        }

        return $actions;
    }

    private array $fieldData = [];
    private ?string $newTreatment = null;

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
}
