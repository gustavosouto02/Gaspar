<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyDemandsWidget;
use App\Filament\Widgets\MySubDemandsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class MyDemands extends BaseDashboard
{
    protected static string $routePath = '/';

    protected static ?string $slug = 'minhas-demandas';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Minhas Demandas';

    protected static ?string $title = 'Minhas Demandas';

    protected static ?int $navigationSort = -2;

    public function getHeading(): string
    {
        return '';
    }

    public function getWidgets(): array
    {
        return [
            MyDemandsWidget::class,
            MySubDemandsWidget::class,
        ];
    }
}
