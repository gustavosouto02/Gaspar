<?php

namespace App\Enums;

enum SatisfactionRatingEnum: string
{
    case GREAT   = 'GREAT';
    case GOOD    = 'GOOD';
    case REGULAR = 'REGULAR';
    case BAD     = 'BAD';

    public function label(): string
    {
        return match($this) {
            self::GREAT   => 'Ótimo',
            self::GOOD    => 'Bom',
            self::REGULAR => 'Regular',
            self::BAD     => 'Ruim',
        };
    }

    public function filamentColor(): string
    {
        return match($this) {
            self::GREAT   => 'success',
            self::GOOD    => 'info',
            self::REGULAR => 'warning',
            self::BAD     => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
