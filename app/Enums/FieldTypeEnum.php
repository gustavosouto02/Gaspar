<?php

namespace App\Enums;

enum FieldTypeEnum: string
{
    case TEXT = 'TEXT';
    case TEXTAREA = 'TEXTAREA';
    case NUMBER = 'NUMBER';
    case CURRENCY = 'CURRENCY';
    case DATE = 'DATE';
    case SELECT = 'SELECT';
    case CHECKBOX = 'CHECKBOX';
    case RADIO = 'RADIO';
    case EMAIL = 'EMAIL';

    public static function options(): array
    {
        return [
            self::TEXT->value => 'Texto Simples',
            self::TEXTAREA->value => 'Área de Texto',
            self::NUMBER->value => 'Número',
            self::CURRENCY->value => 'Moeda (R$)',
            self::DATE->value => 'Data',
            self::SELECT->value => 'Caixa de Seleção',
            self::CHECKBOX->value => 'Checkbox',
            self::RADIO->value => 'Botão de Rádio',
            self::EMAIL->value => 'E-mail',
        ];
    }
}
