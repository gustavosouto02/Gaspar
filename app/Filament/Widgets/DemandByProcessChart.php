<?php

namespace App\Filament\Widgets;

use App\Models\CustomEntity;
use App\Models\Demand;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DemandByProcessChart extends ChartWidget
{
    protected static ?string $heading = 'Demandas por Processo';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Demand::select('entity_id', DB::raw('count(*) as total'))
            ->groupBy('entity_id')
            ->pluck('total', 'entity_id')
            ->toArray();

        $entities = CustomEntity::whereIn('id', array_keys($data))->pluck('name', 'id');

        $labels = [];
        $values = [];
        $colors = [];

        $palette = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ef4444', '#14b8a6', '#f43f5e'];
        $i = 0;

        foreach ($data as $entityId => $total) {
            $labels[] = $entities[$entityId] ?? 'Processo Removido';
            $values[] = $total;
            $colors[] = $palette[$i % count($palette)];
            $i++;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Demandas',
                    'data' => $values,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
