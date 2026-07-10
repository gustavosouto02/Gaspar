<?php

namespace App\Enums;

enum DemandStatusEnum: string
{
    case ACTIVE    = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case CANCELED  = 'CANCELED';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Ativa',
            self::COMPLETED => 'Concluída',
            self::CANCELED  => 'Cancelada',
        };
    }

    public function filamentColor(): string
    {
        return match($this) {
            self::ACTIVE    => 'info',
            self::COMPLETED => 'success',
            self::CANCELED  => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
