<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Campo de telefone obrigatório com DDD: (61) 98888-0000 ou (61) 3333-0000.
 * O valor é gravado já com essa formatação.
 */
class PhoneInput
{
    private const PATTERN = '/^\([1-9]{2}\) (9\d{4}|[2-8]\d{3})-\d{4}$/';

    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->tel()
            ->required()
            ->placeholder('(61) 98888-0000')
            // A máscara muda quando o 11º dígito (celular) é digitado.
            ->mask(RawJs::make("\$input.replace(/\\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999'"))
            // Telefones antigos, gravados sem a formatação, abrem já formatados.
            ->formatStateUsing(fn ($state) => self::format($state))
            ->regex(self::PATTERN)
            ->validationMessages(['regex' => 'Informe o telefone com DDD, por exemplo (61) 98888-0000.']);
    }

    /**
     * Formata quando há DDD e número (10 ou 11 dígitos); qualquer outro valor
     * volta como veio, para a validação apontar o problema.
     */
    public static function format(?string $value): ?string
    {
        // "+55" é o código do país, que o campo não usa.
        $digits = preg_replace('/\D/', '', preg_replace('/^\s*\+55/', '', (string) $value));

        if (preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $digits, $parts) !== 1) {
            return $value;
        }

        return "({$parts[1]}) {$parts[2]}-{$parts[3]}";
    }
}
