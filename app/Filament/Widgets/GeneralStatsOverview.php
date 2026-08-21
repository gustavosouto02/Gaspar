<?php

namespace App\Filament\Widgets;

use App\Enums\DemandStatusEnum;
use App\Models\Demand;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GeneralStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $totalPending = Demand::where('status', '!=', DemandStatusEnum::CLOSED->value)
            ->where('status', '!=', DemandStatusEnum::CANCELED->value)
            ->count();

        $closedThisMonth = Demand::where('status', DemandStatusEnum::CLOSED->value)
            ->whereMonth('completed_at', $currentMonth)
            ->whereYear('completed_at', $currentYear)
            ->count();

        $totalDemands = Demand::count();

        return [
            Stat::make('Demandas Pendentes', $totalPending)
                ->description('Total em andamento ou abertas')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Encerradas (Mês Atual)', $closedThisMonth)
                ->description('Total concluído neste mês')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Total de Demandas', $totalDemands)
                ->description('Histórico completo')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
        ];
    }
}
