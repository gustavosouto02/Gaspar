<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
use Filament\Resources\Pages\ListRecords;

class ListDemands extends ListRecords
{
    protected static string $resource = DemandResource::class;

    public function mount(): void
    {
        parent::mount();
        // Redireciona para o relatório de demandas, já que a lista padrão foi desativada
        redirect()->to(\App\Filament\Pages\DemandReports::getUrl());
    }
}
