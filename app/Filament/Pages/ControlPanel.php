<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DemandByProcessChart;
use App\Filament\Widgets\DemandSLAChart;
use App\Filament\Widgets\DemandStatsWidget;
use App\Filament\Widgets\GeneralStatsOverview;
use App\Filament\Widgets\ValueChainWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class ControlPanel extends BaseDashboard
{
    protected static string $routePath = 'painel-de-controle';

    protected static ?string $slug = 'painel-de-controle';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationLabel = 'Painel de Controle';

    protected static ?string $title = 'Painel de Controle';

    protected static ?int $navigationSort = -1;

    public function getWidgets(): array
    {
        return [
            DemandStatsWidget::class,
            GeneralStatsOverview::class,
            DemandByProcessChart::class,
            DemandSLAChart::class,
            ValueChainWidget::class,
        ];
    }
}
