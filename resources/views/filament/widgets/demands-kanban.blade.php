@php
    use App\Enums\DemandPriorityEnum;
    use App\Filament\Resources\DemandResource;

    $columns = $widget->getKanbanColumns(collect($records ?? $widget->getTableRecords())->values());
@endphp

<div class="flex gap-4 overflow-x-auto p-4" style="align-items: flex-start;">
    @foreach ($columns as $column)
        <div wire:key="kanban-column-{{ $column['key'] }}"
             class="flex flex-col gap-3 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
             style="flex: 0 0 18rem; width: 18rem;">
            <div class="flex items-center justify-between gap-2">
                <x-filament::badge :color="$column['color']">
                    {{ $column['name'] }}
                </x-filament::badge>

                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ $column['records']->count() }}
                </span>
            </div>

            <div class="flex flex-col gap-2 overflow-y-auto" style="max-height: 32rem;">
                @foreach ($column['records'] as $demand)
                    @php
                        $slaOverdue = $demand->sla_due_at?->isPast();
                    @endphp

                    <a href="{{ DemandResource::getUrl('edit', ['record' => $demand]) }}"
                       wire:key="kanban-card-{{ $demand->id }}"
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
                            #{{ strtoupper(substr($demand->id, 0, 8)) }} · {{ $demand->entity?->full_display_name ?? '—' }}
                        </span>

                        @if ($demand->parent_demand_id && $demand->parent)
                            <span class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                <x-heroicon-o-arrow-turn-left-up class="h-4 w-4 shrink-0" />
                                {{ \Illuminate\Support\Str::limit($demand->parent->title, 40) }}
                            </span>
                        @endif

                        @if ($responsibles = $demand->current_responsibles)
                            <span class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                <x-heroicon-o-user class="h-4 w-4 shrink-0" />
                                {{ \Illuminate\Support\Str::limit($responsibles, 40) }}
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
                @endforeach
            </div>
        </div>
    @endforeach
</div>
