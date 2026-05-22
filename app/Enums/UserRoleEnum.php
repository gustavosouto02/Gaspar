<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case ADMIN = 'ADMIN';
    case GESTOR = 'GESTOR';
    case EXECUTOR = 'EXECUTOR';
    case VIEWER = 'VIEWER';

    public static function options(): array
    {
        return [
            self::ADMIN->value => 'Administrador',
            self::GESTOR->value => 'Gestor',
            self::EXECUTOR->value => 'Executor',
            self::VIEWER->value => 'Visualizador',
        ];
    }
}