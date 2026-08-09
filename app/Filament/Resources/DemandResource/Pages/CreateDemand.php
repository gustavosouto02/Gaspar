<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\DemandFieldValue;
use Filament\Resources\Pages\CreateRecord;

class CreateDemand extends CreateRecord
{
    protected static string $resource = DemandResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * Botão de submit: "Enviar para atendimento"
     */
    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Enviar para atendimento');
    }

    /**
     * Antes de criar, extrai field_data do array de dados
     * e calcula o prazo de atendimento (SLA) baseado no processo.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        // Calcula SLA automaticamente a partir das horas configuradas no processo
        if (! empty($data['entity_id']) && empty($data['sla_due_at'])) {
            $entity = CustomEntity::find($data['entity_id']);
            if ($entity && $entity->sla_hours) {
                $data['sla_due_at'] = now()->addHours($entity->sla_hours);
            }
        }

        return $data;
    }

    /**
     * Após criar a demanda, salva os valores dos campos customizados e auto-atribui.
     */
    protected function afterCreate(): void
    {
        $this->saveFieldValues($this->getRecord()->id, $this->getRecord()->entity_id);
        $this->getRecord()->autoAssign();
    }

    private array $fieldData = [];

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
    }
}
