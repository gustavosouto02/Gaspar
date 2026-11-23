<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use App\Filament\Resources\CustomEntityResource\Pages\Concerns\HasVersioningHeaderActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCustomEntity extends ViewRecord
{
    use HasVersioningHeaderActions;

    protected static string $resource = CustomEntityResource::class;

    protected static string $view = 'filament.resources.custom-entity-resource.pages.view-custom-entity';

    public function getTitle(): string|Htmlable
    {
        return $this->record->display_code ? "{$this->record->display_code} — {$this->record->name}" : $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->publishVersionAction(),
            $this->goToDraftAction(),
            $this->editStructureAction(),
            $this->inactiveVersionsAction(),
            Actions\EditAction::make(),
        ];
    }
}
