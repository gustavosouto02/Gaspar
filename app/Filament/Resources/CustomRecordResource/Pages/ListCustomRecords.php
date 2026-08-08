<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomRecords extends ListRecords
{
    protected static string $resource = CustomRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(fn () => \App\Filament\Resources\CustomRecordResource::getUrl('create', ['type_id' => request()->query('type_id')])),
        ];
    }
}
