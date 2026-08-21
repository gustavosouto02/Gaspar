<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\Url;

class EditCustomRecord extends EditRecord
{
    protected static string $resource = CustomRecordResource::class;

    #[Url]
    public ?string $type_id = null;

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
