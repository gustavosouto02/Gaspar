<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DemandButtonEnum: string implements HasLabel
{
    case SALVAR = 'Salvar';
    case ENVIAR = 'Enviar';

    public function label(): string
    {
        return match ($this) {
            self::SALVAR => 'Salvar',
            self::ENVIAR => 'Enviar',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::SALVAR => 'gray',
            self::ENVIAR => 'success',
        };
    }

    /**
     * Retorna o array associativo com as opções disponíveis para selects do Filament.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Tenta obter o enum a partir de um valor (case-insensitive para tolerância).
     */
    public static function tryFromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0 || strcasecmp($case->name, $value) === 0) {
                return $case;
            }
        }

        return null;
    }
}

// Aliases para máxima compatibilidade semântica
if (! class_exists('App\Enums\DemandButtonActionEnum', false)) {
    class_alias(DemandButtonEnum::class, 'App\Enums\DemandButtonActionEnum');
}

if (! class_exists('App\Enums\DemandActionEnum', false)) {
    class_alias(DemandButtonEnum::class, 'App\Enums\DemandActionEnum');
}
