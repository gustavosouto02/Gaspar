<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
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
     * Antes de criar, extrai field_data do array de dados
     * (não é uma coluna real em demands).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);
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

        $fields = CustomField::where('entity_id', $entityId)->get()->keyBy('key');

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
