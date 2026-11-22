<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Enums\ValueChainFunctionEnum;
use App\Models\Macroprocess;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Macroprocessos de exemplo para testar a visualização da Cadeia de Valor.
 * Uso: php artisan db:seed --class=CadeiaValorSeeder (idempotente).
 */
class CadeiaValorSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('user_role', UserRoleEnum::ADMIN)->first()
            ?? User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $gestor = User::where('user_role', UserRoleEnum::GESTOR)->where('is_active', true)->first()
            ?? User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        $macros = [
            ValueChainFunctionEnum::DEFINIR_DIRETRIZES->value => [
                ['GESTRA', 'Gestão da Estratégia'],
                ['GOVERNA', 'Governança'],
                ['GENOMA', 'Gestão de Normativos'],
                ['PROJE', 'Gestão de Projetos e Processos'],
            ],
            ValueChainFunctionEnum::ENTREGAR->value => [
                ['DENONE', 'Desenvolvimento de Novos Negócios'],
                ['INFIN', 'Investimentos ou Financiamentos'],
                ['HUB', 'Prospecção de Parceiros'],
            ],
            ValueChainFunctionEnum::RELACIONAMENTO->value => [
                ['COSO', 'Comunicação Social'],
                ['RELIN', 'Relações Institucionais'],
            ],
            ValueChainFunctionEnum::SUPORTAR->value => [
                ['GECC', 'Gestão de Construção Civil'],
                ['GENG', 'Gestão de Sistemas de Engenharia'],
                ['INOVA', 'Gestão PD&I'],
                ['GEFIN', 'Gestão Financeira'],
                ['GECONT', 'Gestão Contratual'],
                ['GEPES', 'Gestão de Pessoas'],
            ],
            'sem-classificacao' => [
                ['LEGADO', 'Macroprocesso Legado'],
            ],
        ];

        foreach ($macros as $funcao => $itens) {
            foreach ($itens as [$sigla, $nome]) {
                Macroprocess::updateOrCreate(
                    ['acronym' => $sigla],
                    [
                        'name' => $nome,
                        'description' => "Macroprocesso de exemplo: {$nome}",
                        'value_chain_function' => $funcao === 'sem-classificacao' ? 'Finalístico' : $funcao,
                        'is_active' => true,
                        'manager_id' => $gestor->id,
                        'created_by' => $admin->id,
                    ]
                );
            }
        }
    }
}
