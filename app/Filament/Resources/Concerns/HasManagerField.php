<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait HasManagerField
{
    /**
     * Seletor do Gestor (Macroprocesso e Processo): só lista usuários ativos com perfil
     * GESTOR. Obrigatório e único, na criação e na edição.
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
     * Seletor de Gestores do Cadastro (zero, um ou mais): só lista usuários ativos com
     * perfil GESTOR. Opcional.
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
            // O filtro GESTOR/ativo vale só para as opções. Carregar e salvar sem ele
            // evita vínculo fantasma: um gestor que virou EXECUTOR/inativo continua
            // no estado, aparece como removível e sai da pivô ao salvar.
            ->loadStateFromRelationshipsUsing(static function (Select $component): void {
                $component->state(
                    $component->getRelationship()
                        ->pluck('users.id')
                        ->map(static fn ($id): string => (string) $id)
                        ->all()
                );
            })
            ->getOptionLabelsUsing(static fn (array $values): array => User::withTrashed()->whereKey($values)->pluck('name', 'id')->all())
            ->saveRelationshipsUsing(static fn (Model $record, ?array $state) => $record->managers()->sync($state ?? []))
            ->searchable()
            ->preload()
            ->nestedRecursiveRules([
                Rule::exists('users', 'id')
                    ->where('user_role', UserRoleEnum::GESTOR->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ])
            ->validationMessages([
                'exists' => 'Selecione apenas usuários Gestor ativos.',
            ]);
    }
}
