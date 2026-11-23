<?php

namespace App\Services;

use App\Enums\ProcessStatusColorEnum;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\ProcessStatus;
use Illuminate\Support\Collection;

/**
 * Monta o quadro (Kanban) de demandas de uma versão de processo.
 *
 * As colunas são as situações da versão, exceto a Condicional (que é só um
 * desvio e nunca retém demanda), na ordem do fluxo: calculada pelas transições
 * e não por `display_order`, que é só a ordem de cadastro.
 */
class ProcessKanbanBoard
{
    /** Limite de cards exibidos por coluna; o total real vai no contador. */
    public const CARDS_PER_COLUMN = 25;

    public const OUT_OF_FLOW_KEY = 'out-of-flow';

    /**
     * Colunas do quadro, cada uma com situação, cor, total e os primeiros cards.
     *
     * @return Collection<int, array{key: string, name: string, color: string, total: int, records: Collection, status: ?ProcessStatus}>
     */
    public function columns(CustomEntity $entity): Collection
    {
        $statuses = $this->orderedStatuses($entity);
        $columnIds = $statuses->pluck('id')->all();

        $totals = Demand::query()
            ->where('entity_id', $entity->id)
            ->selectRaw('process_status_id, count(*) as total')
            ->groupBy('process_status_id')
            ->pluck('total', 'process_status_id');

        $columns = $statuses->map(fn (ProcessStatus $status) => [
            'key' => $status->id,
            'name' => $status->name,
            'color' => ProcessStatusColorEnum::tryFrom((string) $status->color)?->filamentColor() ?? 'gray',
            'total' => (int) ($totals[$status->id] ?? 0),
            'records' => $this->cards(
                $entity,
                fn ($q) => $q->where('process_status_id', $status->id),
                $status->isClosed() || $status->isEvaluated() || $status->isCanceled(),
            ),
            'status' => $status,
        ]);

        // Demandas sem situação ou numa situação que não pertence à versão
        $outOfFlowTotal = (int) $totals->reject(fn ($total, $statusId) => in_array($statusId, $columnIds, true))->sum();

        if ($outOfFlowTotal > 0) {
            $columns->push([
                'key' => self::OUT_OF_FLOW_KEY,
                'name' => 'Fora do fluxo',
                'color' => 'gray',
                'total' => $outOfFlowTotal,
                'records' => $this->cards($entity, fn ($q) => $q->where(
                    fn ($w) => $w->whereNull('process_status_id')->orWhereNotIn('process_status_id', $columnIds)
                )),
                'status' => null,
            ]);
        }

        return $columns->values();
    }

    /**
     * Situações da versão (sem a Condicional) na ordem do fluxo.
     *
     * @return Collection<int, ProcessStatus>
     */
    public function orderedStatuses(CustomEntity $entity): Collection
    {
        $statuses = $entity->processStatuses()->withoutConditional()->get()->values();
        $byId = $statuses->keyBy('id');

        $conditionalId = ProcessStatus::findBySystemKey('conditional')?->id;
        $edges = [];

        foreach ($entity->statusTransitions()->with('rules')->get() as $transition) {
            if (! $transition->from_status_id) {
                continue; // "qualquer situação" não define posição no fluxo
            }

            $targets = $transition->to_status_id === $conditionalId
                ? $transition->rules->pluck('to_status_id')->push($transition->default_to_status_id)
                : collect([$transition->to_status_id]);

            foreach ($targets->filter()->unique() as $target) {
                $edges[] = [$transition->from_status_id, $target];
            }
        }

        $initial = $entity->initialProcessStatus()?->id;
        $order = self::flowOrder(
            $statuses->mapWithKeys(fn (ProcessStatus $s) => [$s->id => (int) $s->pivot->display_order])->all(),
            $edges,
            $initial,
        );

        return collect($order)->map(fn (string $id) => $byId[$id])->values();
    }

    /**
     * Ordena nós de um fluxo: camada = maior caminho a partir da inicial, ignorando
     * arestas de retorno (loops). Empate por `display_order`; nós inalcançáveis
     * vão ao fim, também por `display_order`.
     *
     * @param  array<string, int>  $nodes  id => display_order
     * @param  list<array{0: string, 1: string}>  $edges  [de, para]
     * @return list<string>
     */
    public static function flowOrder(array $nodes, array $edges, ?string $initial): array
    {
        $adjacency = [];
        foreach ($edges as [$from, $to]) {
            if ($from !== $to && isset($nodes[$from], $nodes[$to])) {
                $adjacency[$from][$to] = true;
            }
        }
        foreach ($adjacency as $from => $targets) {
            $targets = array_keys($targets);
            usort($targets, fn ($a, $b) => $nodes[$a] <=> $nodes[$b]);
            $adjacency[$from] = $targets;
        }

        $byOrder = fn (array $ids) => collect($ids)->sortBy(fn ($id) => [$nodes[$id], $id])->values()->all();

        if ($initial === null || ! isset($nodes[$initial])) {
            return $byOrder(array_keys($nodes));
        }

        // Busca em profundidade: guarda arestas diretas e pós-ordem (descarta retornos)
        $state = [];
        $postOrder = [];
        $forward = [];
        $visit = function (string $node) use (&$visit, &$state, &$postOrder, &$forward, $adjacency): void {
            $state[$node] = 1;
            foreach ($adjacency[$node] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    continue; // retorno (loop)
                }
                $forward[$node][] = $next;
                if (! isset($state[$next])) {
                    $visit($next);
                }
            }
            $state[$node] = 2;
            $postOrder[] = $node;
        };
        $visit($initial);

        $layer = [$initial => 0];
        foreach (array_reverse($postOrder) as $node) {
            foreach ($forward[$node] ?? [] as $next) {
                $layer[$next] = max($layer[$next] ?? 0, $layer[$node] + 1);
            }
        }

        $reachable = collect(array_keys($layer))
            ->sortBy(fn ($id) => [$layer[$id], $nodes[$id], $id])
            ->values()
            ->all();

        return [...$reachable, ...$byOrder(array_diff(array_keys($nodes), array_keys($layer)))];
    }

    /**
     * Primeiros cards da coluna. Situações em andamento: prazo SLA mais próximo
     * primeiro (sem prazo por último). Situações finais: o prazo não importa mais,
     * então vêm as concluídas mais recentemente primeiro (`completed_at`).
     */
    private function cards(CustomEntity $entity, \Closure $scope, bool $final = false): Collection
    {
        $query = Demand::query()
            ->where('entity_id', $entity->id)
            ->where($scope)
            ->with(['assignee:id,name', 'parent:id,code,title']);

        if ($final) {
            $query->orderByRaw('completed_at is null')->orderByDesc('completed_at');
        } else {
            $query->orderByRaw('sla_due_at is null')->orderBy('sla_due_at');
        }

        return $query->orderByDesc('created_at')->limit(self::CARDS_PER_COLUMN)->get();
    }
}
