@php
    use App\Filament\Resources\DemandResource;
@endphp

<div>
    <x-filament::section
        heading="Quadro de demandas"
        description="Demandas desta versão do processo por situação, na ordem do fluxo. Somente leitura: clique no card para abrir a demanda."
        icon="heroicon-o-view-columns"
        collapsible
    >
        <div class="flex gap-4 overflow-x-auto pb-2" style="align-items: flex-start;">
            @foreach ($columns as $column)
                <div wire:key="process-kanban-column-{{ $column['key'] }}"
                     class="flex flex-col gap-3 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                     style="flex: 0 0 18rem; width: 18rem;">
                    <div class="flex items-center justify-between gap-2">
                        <x-filament::badge :color="$column['color']">
                            {{ $column['name'] }}
                        </x-filament::badge>

                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $column['total'] }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-2 overflow-y-auto" style="max-height: 32rem;">
                        @forelse ($column['records'] as $demand)
                            <x-demand-kanban-card
                                :demand="$demand"
                                :href="DemandResource::getUrl('view', ['record' => $demand])"
                                wire:key="process-kanban-card-{{ $demand->id }}"
                                :responsibles="$demand->assignee?->name" />
                        @empty
                            <span class="py-2 text-center text-xs text-gray-400 dark:text-gray-500">Nenhuma demanda</span>
                        @endforelse
                    </div>

                    @if ($column['total'] > $limit)
                        <a href="{{ DemandResource::getUrl('index', ['tableFilters' => array_filter([
                                'entity_id' => ['value' => $entity->id],
                                'process_status_id' => $column['status'] ? ['value' => $column['status']->id] : null,
                            ])]) }}"
                           class="text-center text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                            +{{ $column['total'] - $limit }} — ver na lista
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</div>
