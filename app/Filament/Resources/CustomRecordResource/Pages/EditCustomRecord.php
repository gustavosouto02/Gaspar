<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomRecord extends EditRecord
{
    protected static string $resource = CustomRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successRedirectUrl(fn () => $this->getResource()::getUrl('index', ['type_id' => $this->record->custom_record_type_id])),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['type_id' => $this->record->custom_record_type_id]);
    }
}
