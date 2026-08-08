<?php

namespace App\Filament\Resources\CustomRecordTypeResource\Pages;

use App\Filament\Resources\CustomRecordTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomRecordType extends CreateRecord
{
    protected static string $resource = CustomRecordTypeResource::class;
}
