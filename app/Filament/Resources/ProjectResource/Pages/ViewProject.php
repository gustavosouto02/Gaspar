<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    public string $new_treatment = '';

    public function addTreatment()
    {
        if (empty(trim($this->new_treatment))) return;

        \App\Models\ProjectProgress::create([
            'project_id' => $this->record->id,
            'user_id' => auth()->id(),
            'content' => $this->new_treatment,
        ]);

        $this->new_treatment = '';

        \Filament\Notifications\Notification::make()
            ->title('Tratamento adicionado com sucesso!')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
