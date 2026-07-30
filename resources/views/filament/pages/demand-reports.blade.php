<x-filament-panels::page>
    <style>
        /* Oculta a barra de pesquisa nativa da tabela, pois usaremos apenas a do topo (header) */
        .fi-ta-search-field {
            display: none !important;
        }
    </style>

    {{ $this->table }}
</x-filament-panels::page>
