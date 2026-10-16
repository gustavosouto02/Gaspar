<?php

namespace App\Filament\Resources\CustomFieldResource\Pages;

use App\Filament\Resources\CustomFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomField extends CreateRecord
{
    protected static string $resource = CustomFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['key']) && ! empty($data['name'])) {
            $data['key'] = str($data['name'])->slug('_')->toString();
        }

        return $data;
    }
}
