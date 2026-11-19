<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomEntity extends ViewRecord
{
    protected static string $resource = CustomEntityResource::class;

    protected static string $view = 'filament.resources.custom-entity-resource.pages.view-custom-entity';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
