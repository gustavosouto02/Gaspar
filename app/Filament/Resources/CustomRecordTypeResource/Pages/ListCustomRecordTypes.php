<?php

namespace App\Filament\Resources\CustomRecordTypeResource\Pages;

use App\Filament\Resources\CustomRecordTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomRecordTypes extends ListRecords
{
    protected static string $resource = CustomRecordTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
