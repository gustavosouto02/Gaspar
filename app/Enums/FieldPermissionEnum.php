<?php

namespace App\Enums;

enum FieldPermissionEnum: string
{
    case REQUIRED = 'REQUIRED';
    case OPTIONAL = 'OPTIONAL';
    case READONLY = 'READONLY';
    case HIDDEN = 'HIDDEN';

    public function label(): string
    {
        return match ($this) {
            self::REQUIRED => 'Obrigatório',
            self::OPTIONAL => 'Opcional',
            self::READONLY => 'Somente leitura',
            self::HIDDEN => 'Oculto',
        };
    }

    /**
     * Cor (token do Filament/Tailwind) para a célula da grade
     */
    public function color(): string
    {
        return match ($this) {
            self::REQUIRED => 'danger',
            self::OPTIONAL => 'success',
            self::READONLY => 'warning',
            self::HIDDEN => 'gray',
        };
    }

    /**
     * Ranking de permissividade: maior = mais permissiva
     * (OPTIONAL > REQUIRED > READONLY > HIDDEN)
     */
    public function rank(): int
    {
        return match ($this) {
            self::OPTIONAL => 3,
            self::REQUIRED => 2,
            self::READONLY => 1,
            self::HIDDEN => 0,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
