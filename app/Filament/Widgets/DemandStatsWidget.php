<?php

namespace App\Filament\Widgets;

use App\Enums\DemandStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Demand;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DemandStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();

        // Minhas demandas pendentes (usando a query inteligente)
        $minhasPendentes = Demand::where('status', DemandStatusEnum::ACTIVE->value)
            ->pendingForUser($user)
            ->count();

        $stats = [
            Stat::make('Minhas Demandas Pendentes', $minhasPendentes)
                ->description('Demandas ativas aguardando sua ação')
                ->icon('heroicon-o-user-circle')
                ->color($minhasPendentes > 0 ? 'warning' : 'success'),
        ];

        // Estatísticas globais (visíveis para todos)
        $totalAtivas = Demand::where('status', DemandStatusEnum::ACTIVE->value)->count();

        $vencidas = Demand::where('status', DemandStatusEnum::ACTIVE->value)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        $concluidasMes = Demand::where('status', DemandStatusEnum::COMPLETED->value)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        array_unshift($stats,
            Stat::make('Total de Demandas Ativas', $totalAtivas)
                ->description('Todas as demandas em andamento no sistema')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('info'),
        );

        $stats[] = Stat::make('Vencidas (SLA)', $vencidas)
            ->description('Demandas ativas com prazo ultrapassado')
            ->icon('heroicon-o-exclamation-triangle')
            ->color($vencidas > 0 ? 'danger' : 'success');

        $stats[] = Stat::make('Concluídas este mês', $concluidasMes)
            ->description('Demandas finalizadas em ' . now()->format('m/Y'))
            ->icon('heroicon-o-check-circle')
            ->color('success');

        return $stats;
    }
}
