<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomEntity extends ViewRecord
{
    protected static string $resource = CustomEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
