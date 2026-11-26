<?php

namespace App\Filament\Resources\CustomRecordTypeResource\Pages;

use App\Filament\Resources\CustomRecordTypeResource;
use App\Models\CustomRecordType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCustomRecordType extends CreateRecord
{
    protected static string $resource = CustomRecordTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // O slug não aparece na tela. A coluna é única, e um cadastro renomeado
        // continua com o slug antigo: por isso o sufixo numérico.
        $base = Str::slug($data['name']);
        $slug = $base;

        for ($i = 2; CustomRecordType::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        $data['slug'] = $slug;

        return $data;
    }
}
