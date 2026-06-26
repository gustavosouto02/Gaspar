<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

use App\Filament\Resources\DemandResource\Pages\Concerns\HasDemandHeaderActions;

class ViewDemand extends ViewRecord
{
    use HasDemandHeaderActions;

    protected static string $resource = DemandResource::class;

    /**
     * Ao carregar o form para visualização, injeta os valores salvos em
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
}
