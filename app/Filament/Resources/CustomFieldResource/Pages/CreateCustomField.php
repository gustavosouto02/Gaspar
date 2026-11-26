<?php

namespace App\Filament\Resources\CustomFieldResource\Pages;

use App\Filament\Resources\CustomFieldResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomField extends CreateRecord
{
    protected static string $resource = CustomFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // A chave não aparece na tela: vem sempre do nome.
        $data['key'] = str($data['name'])->slug('_')->toString();

        return CustomFieldResource::packLookup($data);
    }
}
