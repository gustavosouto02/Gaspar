<?php

namespace App\Filament\Resources\ProcessRoleResource\Pages;

use App\Filament\Resources\ProcessRoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessRoles extends ListRecords
{
    protected static string $resource = ProcessRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
