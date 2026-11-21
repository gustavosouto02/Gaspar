<?php

namespace Database\Seeders;

use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de teste manual do campo Combo e do histórico de valores (PRs #49 e
 * "histórico de valores"). Só ADICIONA registros marcados com "[TESTE]";
 * pode rodar mais de uma vez sem duplicar.
 *
 *   php artisan db:seed --class=HistoricoCamposTesteSeeder
 *
 * Remover depois: php artisan tinker → rode o trecho de limpeza do passo a passo.
 */
class HistoricoCamposTesteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('user_role', UserRoleEnum::ADMIN)->where('is_active', true)->firstOrFail();
        $gestor = User::where('user_role', UserRoleEnum::GESTOR)->where('is_active', true)->first() ?? $admin;

        // Origem do Combo "certo": cadastro com 3 equipamentos
        $nomeEquip = CustomField::updateOrCreate(
            ['key' => 'teste_nome_equipamento'],
            ['name' => '[TESTE] Nome do equipamento', 'field_type' => FieldTypeEnum::TEXT, 'created_by' => $admin->id]
        );
        $cadastro = CustomRecordType::firstOrCreate(
            ['slug' => 'teste-equipamentos'],
            ['name' => '[TESTE] Equipamentos', 'is_system' => false, 'is_active' => true]
        );
        $cadastro->fields()->syncWithoutDetaching([$nomeEquip->id => ['field_order' => 0]]);
        foreach (['Impressora HP', 'Notebook Dell', 'Projetor Epson'] as $nome) {
            $existe = CustomRecord::where('custom_record_type_id', $cadastro->id)
                ->where('data_json->teste_nome_equipamento', $nome)->exists();
            if (! $existe) {
                CustomRecord::create([
                    'custom_record_type_id' => $cadastro->id,
                    'created_by' => $admin->id,
                    'data_json' => ['teste_nome_equipamento' => $nome],
                ]);
            }
        }

        foreach (['Cliente Alfa', 'Cliente Beta'] as $nome) {
            Client::firstOrCreate(['name' => "[TESTE] {$nome}"], ['is_active' => true]);
        }

        // Campo "errado": Combo ligado a Clientes (deveria ser Equipamentos)
        $campoErrado = CustomField::updateOrCreate(
            ['key' => 'teste_equipamento_errado'],
            [
                'name' => '[TESTE] Equipamento (origem errada)',
                'field_type' => FieldTypeEnum::COMBO,
                'options_json' => ['source' => 'client'],
                'created_by' => $admin->id,
            ]
        );
        $campoTexto = CustomField::updateOrCreate(
            ['key' => 'teste_observacao'],
            ['name' => '[TESTE] Observação', 'field_type' => FieldTypeEnum::TEXT, 'created_by' => $admin->id]
        );

        $processo = CustomEntity::firstOrCreate(
            ['name' => '[TESTE] Processo Histórico'],
            [
                'description' => 'Processo para testar Combo e histórico de valores',
                'is_active' => true,
                'sla_hours' => 48,
                'manager_id' => $gestor->id,
                'created_by' => $admin->id,
            ]
        );
        $processo->fields()->syncWithoutDetaching([
            $campoErrado->id => ['field_order' => 0],
            $campoTexto->id => ['field_order' => 1],
        ]);

        $situacao = $processo->processStatuses()->where('system_key', 'new')->firstOrFail();
        foreach ([$campoErrado, $campoTexto] as $campo) {
            FieldPermission::firstOrCreate(
                ['entity_id' => $processo->id, 'custom_field_id' => $campo->id, 'process_status_id' => $situacao->id, 'process_role_id' => null],
                ['permission' => FieldPermissionEnum::OPTIONAL]
            );
        }

        $cliente = Client::where('name', '[TESTE] Cliente Alfa')->firstOrFail();
        $clienteBeta = Client::where('name', '[TESTE] Cliente Beta')->firstOrFail();

        foreach ([['Demanda A', $cliente, 'primeira observação'], ['Demanda B', $clienteBeta, 'segunda observação']] as [$titulo, $valorCombo, $obs]) {
            $demanda = Demand::where('title', "[TESTE] {$titulo}")->first() ?? Demand::factory()->create([
                'title' => "[TESTE] {$titulo}",
                'entity_id' => $processo->id,
                'client_id' => $cliente->id,
                'process_status_id' => $situacao->id,
                'requested_by' => $admin->id,
                'created_by' => $admin->id,
                'assigned_to' => $admin->id,
                'parent_demand_id' => null,
            ]);

            DemandFieldValue::updateOrCreate(
                ['demand_id' => $demanda->id, 'custom_field_id' => $campoErrado->id],
                ['value' => $valorCombo->id]
            );
            DemandFieldValue::updateOrCreate(
                ['demand_id' => $demanda->id, 'custom_field_id' => $campoTexto->id],
                ['value' => $obs]
            );
        }

        $this->command?->info("Pronto. Processo: {$processo->name} | login admin: {$admin->email}");
    }
}
