<?php

namespace App\Filament\Resources\MacroprocessResource\Pages;

use App\Filament\Resources\MacroprocessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMacroprocess extends EditRecord
{
    use \App\Filament\Resources\Concerns\HidesMoneyValuesOnFill;

    protected static string $resource = MacroprocessResource::class;
}
