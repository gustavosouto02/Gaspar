<?php

namespace App\Enums;

enum DemandPriorityEnum: string
{
    case LOW    = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH   = 'HIGH';
    case URGENT = 'URGENT';

    public function label(): string
    {
        return match($this) {
            self::LOW    => 'Baixa',
            self::MEDIUM => 'Média',
            self::HIGH   => 'Alta',
            self::URGENT => 'Urgente',
        };
    }

    public function filamentColor(): string
    {
        return match($this) {
            self::LOW    => 'gray',
            self::MEDIUM => 'info',
            self::HIGH   => 'warning',
            self::URGENT => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
