<?php

namespace App\Enums;

enum DemandStatusEnum: string
{
    case ACTIVE    = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case CANCELED  = 'CANCELED';
    case CLOSED    = 'CLOSED';
    case EVALUATED = 'EVALUATED';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Ativa',
            self::COMPLETED => 'Concluída',
            self::CANCELED  => 'Cancelada',
            self::CLOSED    => 'Encerrada',
            self::EVALUATED => 'Avaliada',
        };
    }

    public function filamentColor(): string
    {
        return match($this) {
            self::ACTIVE    => 'info',
            self::COMPLETED => 'success',
            self::CANCELED  => 'danger',
            self::CLOSED    => 'warning',
            self::EVALUATED => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
