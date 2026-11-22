@php
    use App\Enums\ValueChainFunctionEnum;

    $groups = $this->getGroups();
    $blocks = [
        ValueChainFunctionEnum::DEFINIR_DIRETRIZES->value => 'vc-area-diretrizes',
        ValueChainFunctionEnum::ENTREGAR->value => 'vc-area-entregar',
        ValueChainFunctionEnum::RELACIONAMENTO->value => 'vc-area-relacionamento',
        ValueChainFunctionEnum::SUPORTAR->value => 'vc-area-suportar',
    ];
@endphp

<x-filament-widgets::widget>
    <style>
        .vc-board { display: grid; gap: .75rem; grid-template-columns: 1fr; }
        .vc-side { display: flex; align-items: center; justify-content: center; border-radius: .75rem; padding: .5rem; font-weight: 600; letter-spacing: .15em; font-size: .75rem; text-transform: uppercase; }
        .vc-core { display: grid; gap: .75rem; grid-template-columns: 1fr; }
        .vc-block { border-radius: .75rem; padding: .75rem; }
        .vc-title { font-style: italic; font-weight: 600; font-size: .9rem; margin-bottom: .6rem; }
        .vc-items { display: flex; flex-direction: column; gap: .5rem; }
        .vc-chip { display: flex; align-items: center; justify-content: center; text-align: center; min-height: 3.25rem; padding: .4rem 1.4rem; font-size: .75rem; font-weight: 500; line-height: 1.2; text-decoration: none;
            clip-path: polygon(0 0, calc(100% - 1rem) 0, 100% 50%, calc(100% - 1rem) 100%, 0 100%, 1rem 50%); transition: filter .15s; }
        .vc-chip:hover { filter: brightness(.93); }
        .vc-empty { font-size: .75rem; font-style: italic; opacity: .6; }
        .vc-out { display: none; }
        @media (min-width: 1024px) {
            .vc-side-left { writing-mode: vertical-rl; }
            .vc-side-left { transform: rotate(180deg); }
            .vc-core { grid-template-columns: 1fr 2fr; grid-template-areas: "diretrizes entregar" "diretrizes relacionamento" "diretrizes suportar"; }
            .vc-area-diretrizes { grid-area: diretrizes; } .vc-area-entregar { grid-area: entregar; }
            .vc-area-relacionamento { grid-area: relacionamento; } .vc-area-suportar { grid-area: suportar; }
            .vc-items-row { flex-direction: row; flex-wrap: wrap; }
            .vc-items-row .vc-chip { flex: 1 1 10rem; }
            .vc-board { grid-template-columns: 2.5rem 1fr 6rem; }
            .vc-out { display: block; position: relative; padding: 0; overflow: hidden; min-height: 22rem; container-type: size;
                clip-path: polygon(0 0, 55% 0, 100% 50%, 55% 100%, 0 100%, 45% 50%); }
            .vc-out span { position: absolute; left: 50%; white-space: nowrap; font-size: .7rem; font-weight: 700; letter-spacing: .12em; }
            .vc-out-top { top: 25%; transform: translate(-50%, -50%) rotate(72deg); transform: translate(-50%, -50%) rotate(atan2(50cqh, 45cqw)); }
            .vc-out-bottom { top: 75%; transform: translate(-50%, -50%) rotate(-72deg); transform: translate(-50%, -50%) rotate(calc(-1 * atan2(50cqh, 45cqw))); }
        }
        .vc-side, .vc-out { background: rgb(var(--primary-600)); color: #fff; }
        .vc-block { background: rgb(var(--gray-50)); box-shadow: inset 0 0 0 1px rgb(var(--gray-950) / .08); }
        .vc-chip { background: #fff; color: rgb(var(--gray-900)); }
        .dark .vc-block { background: rgb(255 255 255 / .05); box-shadow: inset 0 0 0 1px rgb(255 255 255 / .1); }
        .dark .vc-chip { background: rgb(var(--gray-700)); color: #fff; }
    </style>

    <x-filament::section heading="Cadeia de Valor" description="Macroprocessos ativos agrupados por função. Clique para abrir.">
        <div class="vc-board">
            <div class="vc-side vc-side-left">Parceiros &amp; Fornecedores</div>

            <div class="vc-core">
                @foreach ($blocks as $value => $area)
                    <section class="vc-block {{ $area }}" wire:key="vc-{{ $area }}">
                        <h3 class="vc-title">{{ $value }}</h3>
                        <div class="vc-items {{ $value === ValueChainFunctionEnum::DEFINIR_DIRETRIZES->value ? '' : 'vc-items-row' }}">
                            @forelse ($groups[$value] as $item)
                                <a href="{{ $item['url'] }}" class="vc-chip">{{ $item['label'] }}</a>
                            @empty
                                <span class="vc-empty">Nenhum macroprocesso</span>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="vc-side vc-out" aria-label="Sociedade e Resultados"><span class="vc-out-top">SOCIEDADE</span><span class="vc-out-bottom">RESULTADOS</span></div>
        </div>

        @if ($groups['unclassified']->isNotEmpty())
            <div class="vc-block mt-4" wire:key="vc-unclassified">
                <h3 class="vc-title">Não classificado</h3>
                <div class="vc-items vc-items-row" style="flex-direction: row; flex-wrap: wrap;">
                    @foreach ($groups['unclassified'] as $item)
                        <a href="{{ $item['url'] }}" class="vc-chip" style="flex: 0 1 12rem;">{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
