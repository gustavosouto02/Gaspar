<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Campo de moeda no padrão brasileiro (1.000,00).
 *
 * Só a entrada e a exibição mudam: o valor continua sendo gravado como número
 * puro com duas casas (1000.00), formato de que dependem a avaliação das
 * regras de transição, os relatórios e o PDF.
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('R$')
            ->placeholder('0,00')
            ->inputMode('decimal')
            ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
            ->formatStateUsing(fn ($state) => self::format($state))
            ->dehydrateStateUsing(fn ($state) => self::normalize($state))
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && self::normalize($value) === null) {
                    $fail('Informe um valor válido, por exemplo 1.000,00.');
                }
            })
            // Ao sair do campo, completa as casas decimais (1.000 vira 1.000,00)
            ->live(onBlur: true)
            ->afterStateUpdated(fn ($state, TextInput $component) => $component->state(self::format($state)));
    }

    /**
     * Converte o que o usuário digitou (1.234,56) ou o que está gravado (1234.5)
     * para o formato de armazenamento ("1234.50"). Devolve null se não for um valor.
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $texto = trim((string) $value);
        $texto = trim((string) preg_replace('/^R\$\s*/u', '', $texto));

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, ',')) {
            // Padrão brasileiro: ponto é milhar, vírgula é decimal
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $texto)) {
            // Só milhares, sem decimais (1.234 ou 1.234.567)
            $texto = str_replace('.', '', $texto);
        }
        // Sem vírgula e fora do padrão de milhar, o ponto é decimal (formato gravado)

        if (! preg_match('/^-?\d+(\.\d+)?$/', $texto)) {
            return null;
        }

        return number_format((float) $texto, 2, '.', '');
    }

    /**
     * Texto do campo: 1.234,56. O que não for um valor volta como veio.
     */
    public static function format(mixed $value): ?string
    {
        $normalizado = self::normalize($value);

        if ($normalizado === null) {
            return $value === null ? null : (string) $value;
        }

        return number_format((float) $normalizado, 2, ',', '.');
    }

    /**
     * Exibição em tabelas e relatórios: R$ 1.234,56.
     */
    public static function display(mixed $value): string
    {
        $texto = self::format($value);

        return $texto === null || $texto === '' ? '—' : 'R$ '.$texto;
    }
}
