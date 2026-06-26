<?php

namespace App\Filament\Resources\ProcessStatusResource\Pages;

use App\Filament\Resources\ProcessStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessStatuses extends ListRecords
{
    protected static string $resource = ProcessStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
