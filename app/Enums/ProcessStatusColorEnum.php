<?php

namespace App\Enums;

enum ProcessStatusColorEnum: string
{
    case GRAY   = 'gray';
    case BLUE   = 'blue';
    case YELLOW = 'yellow';
    case ORANGE = 'orange';
    case GREEN  = 'green';
    case RED    = 'red';
    case PURPLE = 'purple';

    public static function options(): array
    {
        return [
            self::GRAY->value   => 'Cinza',
            self::BLUE->value   => 'Azul',
            self::YELLOW->value => 'Amarelo',
            self::ORANGE->value => 'Laranja',
            self::GREEN->value  => 'Verde',
            self::RED->value    => 'Vermelho',
            self::PURPLE->value => 'Roxo',
        ];
    }

    /**
     * Retorna a cor compatível com os badges do Filament.
     */
    public function filamentColor(): string
    {
        return match($this) {
            self::GRAY   => 'gray',
            self::BLUE   => 'info',
            self::YELLOW => 'warning',
            self::ORANGE => 'warning',
            self::GREEN  => 'success',
            self::RED    => 'danger',
            self::PURPLE => 'primary',
        };
    }
}
