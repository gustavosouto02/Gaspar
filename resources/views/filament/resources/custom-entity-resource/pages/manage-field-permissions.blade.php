<x-filament-panels::page>
    @php
        $cellStyles = [
            'REQUIRED' => 'background-color: rgba(239, 68, 68, 0.12); color: rgb(185, 28, 28); border-color: rgba(239, 68, 68, 0.4);',
            'OPTIONAL' => 'background-color: rgba(34, 197, 94, 0.12); color: rgb(21, 128, 61); border-color: rgba(34, 197, 94, 0.4);',
            'READONLY' => 'background-color: rgba(245, 158, 11, 0.12); color: rgb(180, 83, 9); border-color: rgba(245, 158, 11, 0.4);',
            'HIDDEN'   => 'background-color: rgba(107, 114, 128, 0.15); color: rgb(75, 85, 99); border-color: rgba(107, 114, 128, 0.4);',
            ''         => '',
        ];
    @endphp

    <div class="space-y-6">
        <div class="max-w-md">
            <label for="role-selector" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium">
                Papel
            </label>
            <select
                id="role-selector"
                wire:model.live="roleId"
                class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white"
            >
                <option value="">Todos os papéis (regra genérica)</option>
                @foreach ($this->roles as $roleId => $roleName)
                    <option value="{{ $roleId }}">{{ $roleName }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                A linha do papel vence a regra genérica. Célula "Padrão" herda o comportamento do campo
                (obrigatório/opcional; somente leitura em situações finais).
            </p>
        </div>

        @if ($this->fields->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Este processo não possui campos customizados configurados.
            </p>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-200">Campo</th>
                            @foreach ($this->statuses as $status)
                                <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-200">
                                    {{ $status->name }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($this->fields as $field)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ $field->name }}
                                </td>
                                @foreach ($this->statuses as $status)
                                    @php
                                        $current = $matrix[$field->id][$status->id] ?? '';
                                    @endphp
                                    <td class="px-2 py-2">
                                        <select
                                            wire:model.live="matrix.{{ $field->id }}.{{ $status->id }}"
                                            class="block w-full rounded-lg border text-sm border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                            style="{{ $cellStyles[$current] ?? '' }}"
                                        >
                                            <option value="">Padrão</option>
                                            @foreach ($this->permissionOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                <x-filament::button wire:click="save" icon="heroicon-o-check">
                    Salvar Permissões
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
