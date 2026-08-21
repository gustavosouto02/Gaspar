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

    private bool $isDraft = false;

    /**
     * Botão principal: "Enviar para atendimento" — submissão oficial.
     */
    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Enviar para atendimento');
    }

    /**
     * Esconde o "Criar e criar outro" padrão do Filament.
     */
    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    /**
     * Botões do rodapé: Enviar, Salvar (rascunho) e Cancelar.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            \Filament\Actions\Action::make('salvar')
                ->label('Salvar')
                ->color('gray')
                ->action(function () {
                    $this->isDraft = true;
                    $this->create();
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * Antes de criar, extrai field_data e configura o status:
     * - Rascunho (Salvar): status = DRAFT, sem SLA
     * - Envio (Enviar): status = ACTIVE, calcula SLA
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        if ($this->isDraft) {
            $data['status'] = \App\Enums\DemandStatusEnum::DRAFT->value;
        } else {
            $data['status'] = \App\Enums\DemandStatusEnum::ACTIVE->value;

            // Calcula SLA somente ao enviar para atendimento
            if (! empty($data['entity_id']) && empty($data['sla_due_at'])) {
                $entity = CustomEntity::find($data['entity_id']);
                if ($entity && $entity->sla_hours) {
                    $data['sla_due_at'] = now()->addHours($entity->sla_hours);
                }
            }
        }

        return $data;
    }

    /**
     * Após criar: salva campos customizados sempre.
     * AutoAssign só roda ao enviar para atendimento (não no rascunho).
     */
    protected function afterCreate(): void
    {
        $this->saveFieldValues($this->getRecord()->id, $this->getRecord()->entity_id);

        if (! $this->isDraft) {
            $this->getRecord()->autoAssign();
        }
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
