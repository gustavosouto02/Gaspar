<?php

namespace App\Filament\Resources\CustomFieldResource\Pages;

use App\Enums\FieldTypeEnum;
use App\Filament\Resources\CustomFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomField extends EditRecord
{
    protected static string $resource = CustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CustomFieldResource::unpackLookup($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        // A chave nunca é editável na tela: o payload não pode alterá-la.
        $data['key'] = $record->key;

        // Garantia no servidor: com valores gravados, tipo e origem não mudam
        // mesmo que o payload tente alterar o campo desabilitado.
        if ($record->hasStoredValues()) {
            $data['field_type'] = $record->field_type->value;
            if ($record->field_type === FieldTypeEnum::COMBO) {
                $data['lookup_source'] = $record->options_json['source'] ?? null;
                $data['lookup_record_type_id'] = $record->options_json['record_type_id'] ?? null;
            }
        }

        return CustomFieldResource::packLookup($data);
    }
}
