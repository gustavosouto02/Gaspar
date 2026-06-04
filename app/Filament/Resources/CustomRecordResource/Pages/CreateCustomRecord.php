<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomRecord extends CreateRecord
{
    protected static string $resource = CustomRecordResource::class;
}
