@php
    use App\Enums\DemandStatusEnum;
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
                    <x-demand-kanban-card
                        :demand="$demand"
                        :href="auth()->user()->can('update', $demand) ? DemandResource::getUrl('edit', ['record' => $demand]) : DemandResource::getUrl('view', ['record' => $demand])"
                        wire:key="kanban-card-{{ $demand->id }}"
                        :entity-name="$demand->entity?->full_display_name ?? '—'"
                        :responsibles="$demand->current_responsibles"
                        :finished-can-be-overdue="true">
                        <x-slot:notice>
                            @if ($demand->requested_by === auth()->id() && empty($demand->satisfaction_rating) && ($demand->status === DemandStatusEnum::CLOSED || $demand->processStatus?->isClosed()))
                                <span class="flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                    <x-heroicon-m-star class="h-4 w-4 shrink-0 text-amber-500" />
                                    Aguardando sua avaliação
                                </span>
                            @endif
                        </x-slot:notice>
                    </x-demand-kanban-card>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
