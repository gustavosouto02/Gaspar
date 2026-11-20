<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

use App\Filament\Resources\DemandResource\Pages\Concerns\HasDemandHeaderActions;
use App\Services\MoneyFieldVisibility;

class ViewDemand extends ViewRecord
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

        $entity = $this->getRecord()->entity;
        $moneyVisibility = app(MoneyFieldVisibility::class);

        foreach ($fieldValues as $fv) {
            if (! $fv->customField) {
                continue;
            }

            // O valor de campo de valor nem chega ao navegador (o estado do
            // Livewire é serializado) para quem não pode vê-lo
            if ($moneyVisibility->isHiddenFor(auth()->user(), $fv->customField, $entity)) {
                continue;
            }

            $data['field_data'][$fv->customField->key] = $fv->value;
        }

        return $data;
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Resources\DemandResource\Widgets\DemandEvaluationWidget::class,
        ];
    }
}
