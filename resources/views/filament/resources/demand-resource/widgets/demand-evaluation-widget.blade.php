<x-filament-widgets::widget @class(['hidden' => !($record && $record->satisfaction_rating)])>
    @if ($record && $record->satisfaction_rating)
        <x-filament::section>
            <x-slot name="heading">
                Resultado da Avaliação (Pesquisa de Satisfação)
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Demandante</p>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->requester?->name ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Papel no Processo</p>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        @php
                            $member = $record->entity?->members()->where('user_id', $record->requested_by)->first();
                            $role = $member ? $member->processRole?->name : 'Sem Papel Definido';
                        @endphp
                        {{ $role }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Data e Hora da Avaliação</p>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->satisfaction_evaluated_at ? $record->satisfaction_evaluated_at->format('d/m/Y \à\s H:i') : '-' }}
                    </p>
                </div>
                <div class="col-span-2 md:col-span-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nota de Avaliação</p>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->satisfaction_rating->label() }}
                    </p>
                </div>
                <div class="col-span-2 md:col-span-4 mt-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Comentário</p>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->satisfaction_comment ?? '-' }}
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
