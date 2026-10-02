<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\ProcessStatusColorEnum;
use App\Models\Demand;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Session;

/**
 * Alterna um widget de tabela de demandas entre lista e quadro (Kanban)
 * agrupado por situação, mantendo busca e filtros da tabela.
 */
trait HasKanbanView
{
    /**
     * Modo de visualização: 'list' (tabela) ou 'kanban' (quadro por situação).
     * Guardado na sessão para manter a preferência entre acessos ao painel.
     */
    #[Session]
    public string $viewMode = 'list';

    public function isKanban(): bool
    {
        return $this->viewMode === 'kanban';
    }

    protected function applyKanbanView(Table $table, array $paginationPageOptions = [10, 25, 50]): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\Action::make('view_list')
                    ->label('Lista')
                    ->icon('heroicon-o-list-bullet')
                    ->size('sm')
                    ->color(fn () => $this->isKanban() ? 'gray' : 'primary')
                    ->action(fn () => $this->viewMode = 'list'),

                Tables\Actions\Action::make('view_kanban')
                    ->label('Quadro')
                    ->icon('heroicon-o-view-columns')
                    ->size('sm')
                    ->color(fn () => $this->isKanban() ? 'primary' : 'gray')
                    ->action(fn () => $this->viewMode = 'kanban'),
            ])
            // No modo quadro os registros são renderizados pela view do Kanban,
            // mantendo busca e filtros da tabela
            ->content(fn () => $this->isKanban()
                ? view('filament.widgets.demands-kanban', ['widget' => $this])
                : null)
            ->paginated(fn () => ! $this->isKanban())
            ->paginationPageOptions($paginationPageOptions);
    }

    /**
     * Agrupa as demandas em colunas por situação para o modo quadro.
     *
     * Como uma mesma situação pode ser usada por vários processos, a ordem das
     * colunas segue o menor display_order da situação entre os processos das
     * demandas exibidas. Demandas sem situação ficam numa coluna ao final.
     */
    public function getKanbanColumns(Collection $records): Collection
    {
        $statusIds = $records->pluck('process_status_id')->filter()->unique();

        $orderByStatus = $statusIds->isEmpty()
            ? collect()
            : DB::table('entity_process_status')
                ->whereIn('process_status_id', $statusIds)
                ->whereIn('entity_id', $records->pluck('entity_id')->filter()->unique())
                ->groupBy('process_status_id')
                ->selectRaw('process_status_id, MIN(display_order) as min_order')
                ->pluck('min_order', 'process_status_id');

        return $records
            ->groupBy(fn (Demand $record) => $record->process_status_id ?? '')
            ->map(function (Collection $demands, string $statusId) use ($orderByStatus) {
                $status = $demands->first()->processStatus;

                return [
                    'key' => $statusId ?: 'none',
                    'name' => $status?->name ?? 'Sem situação',
                    'color' => $status
                        ? ProcessStatusColorEnum::tryFrom($status->color)?->filamentColor() ?? 'gray'
                        : 'gray',
                    'order' => $statusId !== '' ? (int) ($orderByStatus[$statusId] ?? PHP_INT_MAX - 1) : PHP_INT_MAX,
                    'records' => $demands,
                ];
            })
            ->sortBy([['order', 'asc'], ['name', 'asc']])
            ->values();
    }
}
