<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\Url;

class ViewCustomRecord extends ViewRecord
{
    protected static string $resource = CustomRecordResource::class;

    #[Url]
    public ?string $type_id = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
