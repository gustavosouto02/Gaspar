<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case ADMIN = 'admin';
    case GESTOR = 'gestor';
    case EXECUTOR = 'executor';
    case VIEWER = 'viewer';
}
