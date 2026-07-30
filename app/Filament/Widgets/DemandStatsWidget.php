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
        $userId = $user->id;

        // Minhas demandas (demandas pai onde sou participante)
        $minhasDemandas = Demand::where('status', DemandStatusEnum::ACTIVE->value)
            ->whereNull('parent_demand_id')
            ->pendingForUser($user)
            ->count();

        // Demandas atrasadas (SLA vencido, minhas)
        $demandasAtrasadas = Demand::where('status', DemandStatusEnum::ACTIVE->value)
            ->whereNull('parent_demand_id')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->pendingForUser($user)
            ->count();

        // Minhas subdemandas
        $minhasSubdemandas = Demand::where('status', DemandStatusEnum::ACTIVE->value)
            ->whereNotNull('parent_demand_id')
            ->pendingForUser($user)
            ->count();

        // Subdemandas atrasadas
        $subdemandasAtrasadas = Demand::where('status', DemandStatusEnum::ACTIVE->value)
            ->whereNotNull('parent_demand_id')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->pendingForUser($user)
            ->count();

        // Projetos que sou membro
        $projetosMembro = \App\Models\Project::whereHas('members', function ($q) use ($userId) {
            $q->where('users.id', $userId);
        })->count();

        // Processos que sou membro
        $processosMembro = \App\Models\CustomEntity::whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        return [
            Stat::make('Qtde de Demandas', $minhasDemandas)
                ->description('Demandas ativas sob sua responsabilidade')
                ->icon('heroicon-o-clipboard-document-list')
                ->color($minhasDemandas > 0 ? 'warning' : 'success'),

            Stat::make('Demandas Atrasadas', $demandasAtrasadas)
                ->description('Demandas com prazo vencido')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($demandasAtrasadas > 0 ? 'danger' : 'success'),

            Stat::make('Qtde de Subdemandas', $minhasSubdemandas)
                ->description('Subdemandas ativas sob sua responsabilidade')
                ->icon('heroicon-o-document-duplicate')
                ->color($minhasSubdemandas > 0 ? 'warning' : 'success'),

            Stat::make('Subdemandas Atrasadas', $subdemandasAtrasadas)
                ->description('Subdemandas com prazo vencido')
                ->icon('heroicon-o-exclamation-circle')
                ->color($subdemandasAtrasadas > 0 ? 'danger' : 'success'),

            Stat::make('Projetos (membro)', $projetosMembro)
                ->description('Projetos que você participa')
                ->icon('heroicon-o-briefcase')
                ->color('info'),

            Stat::make('Processos (membro)', $processosMembro)
                ->description('Processos que você participa')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('info'),
        ];
    }
}
