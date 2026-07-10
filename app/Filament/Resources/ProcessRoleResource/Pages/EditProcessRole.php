<?php

namespace App\Filament\Resources\ProcessRoleResource\Pages;

use App\Filament\Resources\ProcessRoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcessRole extends EditRecord
{
    protected static string $resource = ProcessRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
