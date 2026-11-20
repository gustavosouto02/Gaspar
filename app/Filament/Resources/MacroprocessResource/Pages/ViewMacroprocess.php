<?php

namespace App\Filament\Resources\MacroprocessResource\Pages;

use App\Filament\Resources\Concerns\HidesMoneyValuesOnFill;
use App\Filament\Resources\MacroprocessResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMacroprocess extends ViewRecord
{
    use HidesMoneyValuesOnFill;

    protected static string $resource = MacroprocessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
