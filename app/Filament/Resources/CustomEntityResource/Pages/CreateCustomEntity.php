<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomEntity extends CreateRecord
{
    protected static string $resource = CustomEntityResource::class;
}
