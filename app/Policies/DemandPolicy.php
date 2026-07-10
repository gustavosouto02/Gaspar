<?php

namespace App\Policies;

use App\Enums\DemandStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Demand;
use App\Models\User;

class DemandPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Demand $demand): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Demand $demand): bool
    {
        // Se a demanda ainda não foi salva no banco (criando), permite.
        if (! $demand->exists) {
            return true;
        }

        // ADMIN sempre pode editar
        if ($user->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        // Se o usuário tem permissão para disparar alguma transição a partir da
        // situação atual (ex: Gestor na situação "Em Análise"), ele pode editar a demanda.
        // Se a demanda acabou de ser criada (situação nula) e ele é o demandante, ele tem uma
        // transição disponível (ex: Enviar) ou então ainda é a fase inicial dele.
        return $demand->canBeTransitionedBy($user);
    }

    public function delete(User $user, Demand $demand): bool
    {
        return $user->user_role === UserRoleEnum::ADMIN;
    }
}
