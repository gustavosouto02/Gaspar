<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomEntities extends ListRecords
{
    protected static string $resource = CustomEntityResource::class;

    /**
     * A listagem mostra as versões vigentes e os rascunhos; as antigas ficam em "Versões inativas", dentro de cada processo
     */
    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->currentOrDraft()
            ->with(['rootEntity', 'macroprocess', 'manager', 'creator']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
