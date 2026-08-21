<?php

namespace App\Filament\Resources\ProcessRoleResource\Pages;

use App\Filament\Resources\ProcessRoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProcessRole extends ViewRecord
{
    protected static string $resource = ProcessRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
