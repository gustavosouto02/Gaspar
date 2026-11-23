@props([
    'demand',
    'href',
    // Texto do processo ao lado do código (null = não mostra)
    'entityName' => null,
    // Responsável(is) já formatado(s) (null = não mostra)
    'responsibles' => null,
    // Widget do painel: prazo passado marca atraso mesmo em situação final (inclusive Concluída, que ali
    // aguarda avaliação); o quadro do processo mantém o padrão e não marca (comportamento anterior à extração)
    'finishedCanBeOverdue' => false,
])

@php
    use App\Enums\DemandPriorityEnum;
    use App\Enums\DemandStatusEnum;

    // Demanda concluída, encerrada, avaliada ou cancelada não está mais "atrasada": o prazo deixou de valer
    $finished = in_array($demand->status, [
        DemandStatusEnum::COMPLETED,
        DemandStatusEnum::CLOSED,
        DemandStatusEnum::EVALUATED,
        DemandStatusEnum::CANCELED,
    ], true);
    $slaOverdue = (bool) $demand->sla_due_at?->isPast() && ($finishedCanBeOverdue || ! $finished);
@endphp

<a href="{{ $href }}"
   {{ $attributes->class(['flex flex-col gap-2 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400']) }}>
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

    {{ $notice ?? '' }}

    <span class="text-xs text-gray-500 dark:text-gray-400">
        {{ $demand->code ?? '#' . strtoupper(substr($demand->id, 0, 8)) }}@if ($entityName !== null) · {{ $entityName }}@endif
    </span>

    @if ($demand->parent)
        <span class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
            <x-heroicon-o-arrow-turn-left-up class="h-4 w-4 shrink-0" />
            {{ \Illuminate\Support\Str::limit($demand->parent->title, 40) }}
        </span>
    @endif

    @if ($responsibles)
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
