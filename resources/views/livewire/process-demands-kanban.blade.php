@php
    use App\Enums\DemandPriorityEnum;
    use App\Filament\Resources\DemandResource;
    use App\Services\ProcessKanbanBoard;
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
                            @php
                                $slaOverdue = $demand->sla_due_at?->isPast();
                            @endphp

                            <a href="{{ DemandResource::getUrl('view', ['record' => $demand]) }}"
                               wire:key="process-kanban-card-{{ $demand->id }}"
                               class="flex flex-col gap-2 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ \Illuminate\Support\Str::limit($demand->title, 80) }}
                                    </span>

                                    @if ($demand->priority instanceof DemandPriorityEnum)
                                        <x-filament::badge size="sm" :color="$demand->priority->filamentColor()">
                                            {{ $demand->priority->label() }}
                                        </x-filament::badge>
                                    @endif
                                </div>

                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $demand->code ?? '#' . strtoupper(substr($demand->id, 0, 8)) }}
                                </span>

                                @if ($demand->parent)
                                    <span class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                        <x-heroicon-o-arrow-turn-left-up class="h-4 w-4 shrink-0" />
                                        {{ \Illuminate\Support\Str::limit($demand->parent->title, 40) }}
                                    </span>
                                @endif

                                @if ($demand->assignee)
                                    <span class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                        <x-heroicon-o-user class="h-4 w-4 shrink-0" />
                                        {{ \Illuminate\Support\Str::limit($demand->assignee->name, 40) }}
                                    </span>
                                @endif

                                <div class="flex items-center justify-between gap-2 text-xs">
                                    @if ($demand->sla_due_at)
                                        <span @class([
                                            'flex items-center gap-1',
                                            'font-semibold text-danger-600 dark:text-danger-400' => $slaOverdue,
                                            'text-gray-600 dark:text-gray-300' => ! $slaOverdue,
                                        ])>
                                            <x-dynamic-component
                                                :component="$slaOverdue ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-calendar'"
                                                class="h-4 w-4 shrink-0" />
                                            {{ $demand->sla_due_at->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span></span>
                                    @endif

                                    <span class="text-gray-400 dark:text-gray-500">
                                        {{ $demand->updated_at?->diffForHumans() }}
                                    </span>
                                </div>
                            </a>
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
