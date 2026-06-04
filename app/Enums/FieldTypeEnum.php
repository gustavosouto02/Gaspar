<?php

namespace App\Enums;

enum FieldTypeEnum: string
{
    case TEXT = 'TEXT';
    case TEXTAREA = 'TEXTAREA';
    case NUMBER = 'NUMBER';
    case DATE = 'DATE';
    case SELECT = 'SELECT';
    case CHECKBOX = 'CHECKBOX';
    case EMAIL = 'EMAIL';

    public static function options(): array
    {
        return [
            self::TEXT->value => 'Texto Simples',
            self::TEXTAREA->value => 'Área de Texto',
            self::NUMBER->value => 'Número',
            self::DATE->value => 'Data',
            self::SELECT->value => 'Caixa de Seleção',
            self::CHECKBOX->value => 'Checkbox',
            self::EMAIL->value => 'E-mail',
        ];
    }
}
