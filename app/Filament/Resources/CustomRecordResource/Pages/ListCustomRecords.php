<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

class ListCustomRecords extends ListRecords
{
    protected static string $resource = CustomRecordResource::class;

    #[Url]
    public ?string $type_id = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(fn () => \App\Filament\Resources\CustomRecordResource::getUrl('create', ['type_id' => $this->type_id])),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        if ($this->type_id) {
            $query->where('custom_record_type_id', $this->type_id);
        }
        return $query;
    }
}
