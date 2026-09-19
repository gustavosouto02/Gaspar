<?php

namespace App\Enums;

enum RuleOperatorEnum: string
{
    case EQUALS = 'eq';
    case NOT_EQUALS = 'neq';
    case GREATER = 'gt';
    case GREATER_OR_EQUAL = 'gte';
    case LESS = 'lt';
    case LESS_OR_EQUAL = 'lte';
    case BETWEEN = 'between';
    case CONTAINS = 'contains';
    case IN_LIST = 'in';
    case IS_TRUE = 'is_true';
    case IS_FALSE = 'is_false';
    case IS_EMPTY = 'empty';
    case IS_FILLED = 'filled';

    public function label(): string
    {
        return match ($this) {
            self::EQUALS => 'Igual a',
            self::NOT_EQUALS => 'Diferente de',
            self::GREATER => 'Maior que',
            self::GREATER_OR_EQUAL => 'Maior ou igual a',
            self::LESS => 'Menor que',
            self::LESS_OR_EQUAL => 'Menor ou igual a',
            self::BETWEEN => 'Entre',
            self::CONTAINS => 'Contém',
            self::IN_LIST => 'Em lista',
            self::IS_TRUE => 'Sim',
            self::IS_FALSE => 'Não',
            self::IS_EMPTY => 'Vazio',
            self::IS_FILLED => 'Preenchido',
        };
    }

    /**
     * Símbolo curto para resumos ("Se Valor > 1000 → Em aprovação")
     */
    public function symbol(): string
    {
        return match ($this) {
            self::EQUALS => '=',
            self::NOT_EQUALS => '!=',
            self::GREATER => '>',
            self::GREATER_OR_EQUAL => '>=',
            self::LESS => '<',
            self::LESS_OR_EQUAL => '<=',
            default => mb_strtolower($this->label()),
        };
    }

    /**
     * Operadores válidos por tipo de campo
     *
     * @return array<self>
     */
    public static function forFieldType(FieldTypeEnum $type): array
    {
        $common = [self::IS_EMPTY, self::IS_FILLED];

        return match ($type) {
            FieldTypeEnum::NUMBER, FieldTypeEnum::DATE => [
                self::EQUALS, self::NOT_EQUALS,
                self::GREATER, self::GREATER_OR_EQUAL,
                self::LESS, self::LESS_OR_EQUAL,
                self::BETWEEN,
                ...$common,
            ],
            FieldTypeEnum::TEXT, FieldTypeEnum::TEXTAREA, FieldTypeEnum::EMAIL => [
                self::EQUALS, self::NOT_EQUALS, self::CONTAINS,
                ...$common,
            ],
            FieldTypeEnum::SELECT, FieldTypeEnum::RADIO => [
                self::EQUALS, self::IN_LIST,
                ...$common,
            ],
            FieldTypeEnum::CHECKBOX => [
                self::IS_TRUE, self::IS_FALSE,
                ...$common,
            ],
        };
    }

    /**
     * Opções [value => label] para selects do Filament
     *
     * @return array<string, string>
     */
    public static function optionsForFieldType(FieldTypeEnum $type): array
    {
        $options = [];
        foreach (self::forFieldType($type) as $operator) {
            $options[$operator->value] = $operator->label();
        }

        return $options;
    }

    /**
     * Formato de valor exigido pelo operador
     */
    public function valueKind(): string
    {
        return match ($this) {
            self::BETWEEN => 'range',
            self::IN_LIST => 'list',
            self::IS_TRUE, self::IS_FALSE, self::IS_EMPTY, self::IS_FILLED => 'none',
            default => 'single',
        };
    }
}
