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
     * Antes de salvar, extrai field_data (não é coluna real em demands).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);
        return $data;
    }

    /**
     * Após salvar, atualiza demand_field_values.
     */
    protected function afterSave(): void
    {
        $this->saveFieldValues(
            $this->getRecord()->id,
            $this->getRecord()->entity_id
        );
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

        // Remove valores de campos que não existem mais no processo
        $validFieldIds = $fields->pluck('id')->all();
        DemandFieldValue::where('demand_id', $demandId)
            ->whereNotIn('custom_field_id', $validFieldIds)
            ->delete();
    }
}
