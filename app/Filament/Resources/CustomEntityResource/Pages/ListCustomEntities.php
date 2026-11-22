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
     * A listagem mostra só as versões vigentes; as antigas ficam em "Versões inativas"
     */
    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->current()
            ->with(['rootEntity', 'macroprocess', 'manager', 'creator']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('inactive_versions')
                ->label('Versões inativas')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->url(fn () => CustomEntityResource::getUrl('inactive-versions')),

            Actions\CreateAction::make(),
        ];
    }
}
