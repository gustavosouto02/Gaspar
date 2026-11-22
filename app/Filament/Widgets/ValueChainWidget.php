<?php

namespace App\Filament\Widgets;

use App\Enums\ValueChainFunctionEnum;
use App\Filament\Resources\MacroprocessResource;
use App\Models\Macroprocess;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class ValueChainWidget extends Widget
{
    protected static string $view = 'filament.widgets.value-chain';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    /**
     * Macroprocessos ativos agrupados pela função na cadeia de valor.
     * A chave `null` reúne os sem classificação ou com valor fora do enum.
     *
     * @return array<string, Collection<int, array{id: string, label: string, url: string}>>
     */
    public function getGroups(): array
    {
        $macros = Macroprocess::query()
            ->where('is_active', true)
            ->orderBy('acronym')
            ->orderBy('name')
            ->get();

        $valid = array_column(ValueChainFunctionEnum::cases(), 'value');
        $groups = [];

        foreach (ValueChainFunctionEnum::cases() as $case) {
            $groups[$case->value] = $macros->where('value_chain_function', $case->value);
        }

        $groups['unclassified'] = $macros->reject(
            fn (Macroprocess $m) => in_array($m->value_chain_function, $valid, true)
        );

        return array_map(
            fn (Collection $items) => $items->map(fn (Macroprocess $m) => [
                'id' => $m->id,
                'label' => $this->label($m),
                'url' => MacroprocessResource::getUrl('view', ['record' => $m]),
            ])->values(),
            $groups,
        );
    }

    /** "SIGLA - Nome", sem repetir a sigla quando o nome já começa com ela. */
    private function label(Macroprocess $macro): string
    {
        $name = trim((string) $macro->name);

        if (! $macro->acronym) {
            return $name;
        }

        $acronym = trim($macro->acronym);
        $name = (string) preg_replace('/^'.preg_quote($acronym, '/').'\s*-\s*/iu', '', $name);

        return "{$acronym} - {$name}";
    }
}
