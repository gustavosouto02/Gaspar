<?php

namespace App\Filament\Widgets;

use App\Models\Demand;
use Filament\Widgets\ChartWidget;

class DemandSLAChart extends ChartWidget
{
    protected static ?string $heading = 'Cumprimento de Prazo (SLA)';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $onTime = Demand::whereNotNull('completed_at')
            ->whereNotNull('sla_due_at')
            ->whereColumn('completed_at', '<=', 'sla_due_at')
            ->count();

        $late = Demand::whereNotNull('completed_at')
            ->whereNotNull('sla_due_at')
            ->whereColumn('completed_at', '>', 'sla_due_at')
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Demandas',
                    'data' => [$onTime, $late],
                    'backgroundColor' => ['#10b981', '#ef4444'], // Green for on time, Red for late
                ],
            ],
            'labels' => ['Dentro do Prazo', 'Fora do Prazo'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
