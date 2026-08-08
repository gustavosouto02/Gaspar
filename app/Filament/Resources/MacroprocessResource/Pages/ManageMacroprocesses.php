<?php

namespace App\Filament\Resources\MacroprocessResource\Pages;

use App\Filament\Resources\MacroprocessResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMacroprocesses extends ManageRecords
{
    protected static string $resource = MacroprocessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
