<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\UserRoleEnum;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

trait HasManagerField
{
    /**
     * Seletor obrigatório do Gestor: só lista usuários ativos com perfil GESTOR.
     */
    protected static function managerSelect(): Select
    {
        return Select::make('manager_id')
            ->label('Gestor')
            ->relationship(
                'manager',
                'name',
                fn (Builder $query) => $query
                    ->where('user_role', UserRoleEnum::GESTOR)
                    ->where('is_active', true)
            )
            ->searchable()
            ->preload()
            ->required()
            ->rules([
                // Gestor único: rejeita payload com lista de gestores
                'string',
                Rule::exists('users', 'id')
                    ->where('user_role', UserRoleEnum::GESTOR->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ])
            ->validationMessages([
                'required' => 'Informe o gestor.',
                'exists' => 'Selecione um usuário Gestor ativo.',
                'string' => 'Informe apenas um gestor.',
            ]);
    }

    /**
     * Seletor de Gestores (um ou mais, mínimo 1): só lista usuários ativos com perfil GESTOR.
     */
    protected static function managersSelect(): Select
    {
        return Select::make('managers')
            ->label('Gestores')
            ->multiple()
            ->relationship(
                'managers',
                'name',
                fn (Builder $query) => $query
                    ->where('user_role', UserRoleEnum::GESTOR)
                    ->where('is_active', true)
            )
            ->searchable()
            ->preload()
            ->required()
            ->minItems(1)
            ->nestedRecursiveRules([
                Rule::exists('users', 'id')
                    ->where('user_role', UserRoleEnum::GESTOR->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ])
            ->validationMessages([
                'required' => 'Informe ao menos um gestor.',
                'min' => 'Informe ao menos um gestor.',
                'exists' => 'Selecione apenas usuários Gestor ativos.',
            ]);
    }
}
