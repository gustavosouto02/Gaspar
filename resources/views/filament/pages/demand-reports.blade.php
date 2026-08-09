<x-filament-panels::page>
    <style>
        /* Oculta a barra de pesquisa nativa da tabela, pois usaremos apenas a do topo (header) */
        .fi-ta-search-field {
            display: none !important;
        }
    </style>

    <form wire:submit="render">
        {{ $this->form }}
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
