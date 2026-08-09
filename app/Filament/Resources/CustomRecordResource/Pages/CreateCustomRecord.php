<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomRecord extends CreateRecord
{
    protected static string $resource = CustomRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['custom_record_type_id'] = request()->query('type_id');
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['type_id' => request()->query('type_id')]);
    }
}
