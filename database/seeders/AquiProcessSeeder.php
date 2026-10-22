<?php

namespace Database\Seeders;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\RuleOperatorEnum;
use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\FieldPermission;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AquiProcessSeeder extends Seeder
{
    /**
     * Seed do processo oficial de homologação e produção:
     * "AQUI - Contratação de serviços avulsos"
     */
    public function run(): void
    {
        // -------------------------------------------------------------
        // 1. USUÁRIOS DO SISTEMA
        // -------------------------------------------------------------
        $defaultPassword = Hash::make('admin123');

        $admin = User::updateOrCreate(
            ['email' => 'admin@gaspar.com'],
            [
                'name' => 'Administrador Gaspar',
                'password' => Hash::make('admin'),
                'user_role' => UserRoleEnum::ADMIN,
                'phone' => '(11) 98888-0000',
                'is_active' => true,
            ]
        );

        $jose = User::updateOrCreate(
            ['email' => 'adinilson.martins@inct.org.br'],
            [
                'name' => 'José demandante',
                'password' => $defaultPassword,
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone' => '(11) 98888-0001',
                'is_active' => true,
            ]
        );

        $manoel = User::updateOrCreate(
            ['email' => 'adinilson.martins@gmail.com'],
            [
                'name' => 'Manoel licitador',
                'password' => $defaultPassword,
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone' => '(11) 98888-0002',
                'is_active' => true,
            ]
        );

        $joao = User::updateOrCreate(
            ['email' => 'nil@estudandomais.com.br'],
            [
                'name' => 'João aprovador',
                'password' => $defaultPassword,
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone' => '(11) 98888-0003',
                'is_active' => true,
            ]
        );

        $tiao = User::updateOrCreate(
            ['email' => 'adinilson.martins@inct.org'],
            [
                'name' => 'Tião aprova alto',
                'password' => $defaultPassword,
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone' => '(11) 98888-0004',
                'is_active' => true,
            ]
        );

        $maria = User::updateOrCreate(
            ['email' => 'nil@pierconsultoria.com.br'],
            [
                'name' => 'Maria adquirente',
                'password' => $defaultPassword,
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone' => '(11) 98888-0005',
                'is_active' => true,
            ]
        );

        $joana = User::updateOrCreate(
            ['email' => 'plataforma@inct.org.br'],
            [
                'name' => 'Joana quitadora',
                'password' => $defaultPassword,
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone' => '(11) 98888-0006',
                'is_active' => true,
            ]
        );

        // -------------------------------------------------------------
        // 2. CLIENTE E PROJETO
        // -------------------------------------------------------------
        $clienteInct = Client::updateOrCreate(
            ['name' => 'INCT'],
            [
                'email' => 'adinilson.martins@inct.org.br',
                'is_active' => true,
            ]
        );

        $projeto = Project::updateOrCreate(
            ['number' => 'PRJ-INCT-001'],
            [
                'name' => 'Apoio Institucional e Aquisições INCT',
                'description' => 'Projeto de apoio a compras, cotações e contratações do INCT.',
                'client_id' => $clienteInct->id,
                'is_active' => true,
                'start_date_forecast' => now()->startOfYear(),
                'end_date_forecast' => now()->endOfYear(),
                'budget' => 200000.00,
                'spent' => 15000.00,
            ]
        );

        // -------------------------------------------------------------
        // 3. MACROPROCESSO E PROCESSO
        // -------------------------------------------------------------
        $macroprocesso = Macroprocess::updateOrCreate(
            ['name' => 'AQUI - Aquisições'],
            [
                'acronym' => 'AQUI',
                'description' => 'Aquisições, compras e contratação de serviços avulsos',
                'value_chain_function' => 'Apoio',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        $processo = CustomEntity::updateOrCreate(
            ['name' => 'AQUI - Contratação de serviços avulsos'],
            [
                'macroprocess_id' => $macroprocesso->id,
                'description' => 'Processo de contratação de serviços avulsos e cotações',
                'purpose' => 'Apoio',
                'sla_hours' => 120,
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        // -------------------------------------------------------------
        // 4. PAPÉIS DE PROCESSO (Process Roles)
        // -------------------------------------------------------------
        $papeis = [
            'Demandante' => ['color' => 'gray',   'desc' => 'Quem abre e acompanha a solicitação'],
            'Licitador' => ['color' => 'blue',   'desc' => 'Responsável pela cotação/licitação'],
            'Aprovador' => ['color' => 'yellow', 'desc' => 'Aprova contratações de valor regular'],
            'Aprovador alto' => ['color' => 'gray',   'desc' => 'Aprova contratações de valor alto'],
            'Adquirente' => ['color' => 'green',  'desc' => 'Efetiva a contratação do serviço'],
            'Quitador' => ['color' => 'yellow', 'desc' => 'Realiza a quitação e encerra'],
        ];

        $rolesMapeados = [];
        foreach ($papeis as $nome => $dados) {
            $rolesMapeados[$nome] = ProcessRole::updateOrCreate(
                ['name' => $nome],
                [
                    'color' => $dados['color'],
                    'description' => $dados['desc'],
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );
        }

        // Associa cada usuário a seu papel no processo
        $membros = [
            [$joao->id,   $rolesMapeados['Aprovador']->id],
            [$jose->id,   $rolesMapeados['Demandante']->id],
            [$manoel->id, $rolesMapeados['Licitador']->id],
            [$maria->id,  $rolesMapeados['Adquirente']->id],
            [$tiao->id,   $rolesMapeados['Aprovador alto']->id],
            [$joana->id,  $rolesMapeados['Quitador']->id],
        ];

        ProcessMember::where('entity_id', $processo->id)->delete();
        foreach ($membros as [$userId, $roleId]) {
            ProcessMember::create([
                'entity_id' => $processo->id,
                'user_id' => $userId,
                'process_role_id' => $roleId,
            ]);
        }

        // -------------------------------------------------------------
        // 5. CAMPOS CUSTOMIZADOS DO PROCESSO
        // -------------------------------------------------------------
        $camposDefinidos = [
            [
                'name' => 'Preço',
                'key' => 'preco',
                'field_type' => FieldTypeEnum::NUMBER,
                'placeholder' => 'Informe o valor da aquisição',
                'is_required' => true,
                'default_value' => null,
                'options_json' => null,
            ],
            [
                'name' => 'Fornecedor',
                'key' => 'fornecedor',
                'field_type' => FieldTypeEnum::TEXT,
                'placeholder' => 'Insira o nome do fornecedor',
                'is_required' => false,
                'default_value' => null,
                'options_json' => null,
            ],
            [
                'name' => 'Fone do fornecedor',
                'key' => 'fone_do_fornecedor',
                'field_type' => FieldTypeEnum::TEXT,
                'placeholder' => 'Insira o contato do fornecedor',
                'is_required' => false,
                'default_value' => null,
                'options_json' => null,
            ],
            [
                'name' => 'Aprovação',
                'key' => 'aprovacao',
                'field_type' => FieldTypeEnum::RADIO,
                'placeholder' => null,
                'is_required' => false,
                'default_value' => null,
                'options_json' => ['Não aprovado', 'Aprovado'],
            ],
        ];

        $camposMapeados = [];
        $orderField = 1;
        $processo->fields()->detach();

        foreach ($camposDefinidos as $def) {
            $campo = CustomField::updateOrCreate(
                ['key' => $def['key']],
                array_merge($def, ['created_by' => $admin->id])
            );
            $camposMapeados[$def['key']] = $campo;
            $processo->fields()->attach($campo->id, ['field_order' => $orderField++]);
        }

        // -------------------------------------------------------------
        // 6. SITUAÇÕES DO PROCESSO (Process Statuses)
        // -------------------------------------------------------------
        $statusNova = ProcessStatus::where('system_key', 'new')->firstOrFail();
        $statusEncerrada = ProcessStatus::where('system_key', 'closed')->firstOrFail();
        $statusAvaliada = ProcessStatus::where('system_key', 'evaluated')->firstOrFail();
        $statusCancelada = ProcessStatus::where('system_key', 'canceled')->firstOrFail();
        $statusCondicional = ProcessStatus::where('system_key', 'conditional')->firstOrFail();

        $situacoesCustom = [
            'Licitando' => ['color' => 'gray'],
            'Adquirindo' => ['color' => 'gray'],
            'Aprovando' => ['color' => 'red'],
            'Aprovando alto' => ['color' => 'yellow'],
            'Quitando' => ['color' => 'yellow'],
        ];

        $statusMapeados = [];
        foreach ($situacoesCustom as $nome => $dados) {
            $statusMapeados[$nome] = ProcessStatus::updateOrCreate(
                ['name' => $nome],
                [
                    'color' => $dados['color'],
                    'is_system' => false,
                    'system_key' => null,
                    'created_by' => $admin->id,
                ]
            );
        }

        // Vincula as 10 situações na ordem exata vista na interface
        $processo->processStatuses()->detach();
        $todasSituacoes = [
            1 => $statusMapeados['Licitando']->id,
            2 => $statusMapeados['Adquirindo']->id,
            3 => $statusMapeados['Aprovando']->id,
            4 => $statusMapeados['Aprovando alto']->id,
            5 => $statusMapeados['Quitando']->id,
            6 => $statusNova->id,
            7 => $statusEncerrada->id,
            8 => $statusAvaliada->id,
            9 => $statusCancelada->id,
            10 => $statusCondicional->id,
        ];

        foreach ($todasSituacoes as $order => $statusId) {
            $processo->processStatuses()->attach($statusId, ['display_order' => $order]);
        }

        // -------------------------------------------------------------
        // 7. MATRIZ DE PERMISSÕES DE CAMPOS (Tabela da Pág. 5 do E2E)
        // -------------------------------------------------------------
        FieldPermission::where('entity_id', $processo->id)->delete();

        $matriz = [
            // Preço
            ['preco', $statusNova->id, FieldPermissionEnum::HIDDEN],
            ['preco', $statusMapeados['Licitando']->id, FieldPermissionEnum::REQUIRED],
            ['preco', $statusMapeados['Aprovando']->id, FieldPermissionEnum::READONLY],
            ['preco', $statusMapeados['Aprovando alto']->id, FieldPermissionEnum::READONLY],
            ['preco', $statusMapeados['Adquirindo']->id, FieldPermissionEnum::READONLY],
            ['preco', $statusMapeados['Quitando']->id, FieldPermissionEnum::READONLY],

            // Fornecedor
            ['fornecedor', $statusNova->id, FieldPermissionEnum::HIDDEN],
            ['fornecedor', $statusMapeados['Licitando']->id, FieldPermissionEnum::OPTIONAL],
            ['fornecedor', $statusMapeados['Aprovando']->id, FieldPermissionEnum::READONLY],
            ['fornecedor', $statusMapeados['Aprovando alto']->id, FieldPermissionEnum::READONLY],
            ['fornecedor', $statusMapeados['Adquirindo']->id, FieldPermissionEnum::READONLY],
            ['fornecedor', $statusMapeados['Quitando']->id, FieldPermissionEnum::READONLY],

            // Fone do fornecedor
            ['fone_do_fornecedor', $statusNova->id, FieldPermissionEnum::HIDDEN],
            ['fone_do_fornecedor', $statusMapeados['Licitando']->id, FieldPermissionEnum::OPTIONAL],
            ['fone_do_fornecedor', $statusMapeados['Aprovando']->id, FieldPermissionEnum::READONLY],
            ['fone_do_fornecedor', $statusMapeados['Aprovando alto']->id, FieldPermissionEnum::READONLY],
            ['fone_do_fornecedor', $statusMapeados['Adquirindo']->id, FieldPermissionEnum::READONLY],
            ['fone_do_fornecedor', $statusMapeados['Quitando']->id, FieldPermissionEnum::READONLY],

            // Aprovação
            ['aprovacao', $statusNova->id, FieldPermissionEnum::HIDDEN],
            ['aprovacao', $statusMapeados['Licitando']->id, FieldPermissionEnum::HIDDEN],
            ['aprovacao', $statusMapeados['Aprovando']->id, FieldPermissionEnum::REQUIRED],
            ['aprovacao', $statusMapeados['Aprovando alto']->id, FieldPermissionEnum::REQUIRED],
            ['aprovacao', $statusMapeados['Adquirindo']->id, FieldPermissionEnum::READONLY],
            ['aprovacao', $statusMapeados['Quitando']->id, FieldPermissionEnum::READONLY],
        ];

        foreach ($matriz as [$chave, $statusId, $permissao]) {
            FieldPermission::create([
                'entity_id' => $processo->id,
                'custom_field_id' => $camposMapeados[$chave]->id,
                'process_status_id' => $statusId,
                'process_role_id' => null,
                'permission' => $permissao,
            ]);
        }

        // -------------------------------------------------------------
        // 8. TRANSIÇÕES DE STATUS E REGRAS DMN (Fluxo E2E)
        // -------------------------------------------------------------
        StatusTransition::where('entity_id', $processo->id)->delete();

        // 8.1 Nova -> Licitando
        StatusTransition::create([
            'entity_id' => $processo->id,
            'from_status_id' => $statusNova->id,
            'to_status_id' => $statusMapeados['Licitando']->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allow_return' => false,
            'allowed_role_ids' => ['__requester__'],
            'display_order' => 1,
        ]);

        // 8.2 Licitando -> Condicional (Gateway por Faixas de Preço)
        $tLicitando = StatusTransition::create([
            'entity_id' => $processo->id,
            'from_status_id' => $statusMapeados['Licitando']->id,
            'to_status_id' => $statusCondicional->id,
            'default_to_status_id' => $statusMapeados['Adquirindo']->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allow_return' => true,
            'allowed_role_ids' => [$rolesMapeados['Licitador']->id],
            'display_order' => 2,
        ]);

        // Regra 1: Preço > 10.000 -> Aprovando alto
        TransitionRule::create([
            'status_transition_id' => $tLicitando->id,
            'rule_order' => 1,
            'to_status_id' => $statusMapeados['Aprovando alto']->id,
            'conditions' => [
                [
                    'field_id' => $camposMapeados['preco']->id,
                    'operator' => RuleOperatorEnum::GREATER->value,
                    'value' => '10000',
                ],
            ],
        ]);

        // Regra 2: Preço > 2.000 -> Aprovando
        TransitionRule::create([
            'status_transition_id' => $tLicitando->id,
            'rule_order' => 2,
            'to_status_id' => $statusMapeados['Aprovando']->id,
            'conditions' => [
                [
                    'field_id' => $camposMapeados['preco']->id,
                    'operator' => RuleOperatorEnum::GREATER->value,
                    'value' => '2000',
                ],
            ],
        ]);

        // 8.3 Aprovando -> Condicional (Gateway de Aprovação)
        $tAprovando = StatusTransition::create([
            'entity_id' => $processo->id,
            'from_status_id' => $statusMapeados['Aprovando']->id,
            'to_status_id' => $statusCondicional->id,
            'default_to_status_id' => $statusMapeados['Adquirindo']->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allow_return' => true,
            'allowed_role_ids' => [$rolesMapeados['Aprovador']->id],
            'display_order' => 3,
        ]);

        TransitionRule::create([
            'status_transition_id' => $tAprovando->id,
            'rule_order' => 1,
            'to_status_id' => $statusMapeados['Licitando']->id,
            'conditions' => [
                [
                    'field_id' => $camposMapeados['aprovacao']->id,
                    'operator' => RuleOperatorEnum::EQUALS->value,
                    'value' => 'Não aprovado',
                ],
            ],
        ]);

        // 8.4 Aprovando alto -> Condicional (Gateway de Aprovação)
        $tAprovandoAlto = StatusTransition::create([
            'entity_id' => $processo->id,
            'from_status_id' => $statusMapeados['Aprovando alto']->id,
            'to_status_id' => $statusCondicional->id,
            'default_to_status_id' => $statusMapeados['Adquirindo']->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allow_return' => true,
            'allowed_role_ids' => [$rolesMapeados['Aprovador alto']->id],
            'display_order' => 4,
        ]);

        TransitionRule::create([
            'status_transition_id' => $tAprovandoAlto->id,
            'rule_order' => 1,
            'to_status_id' => $statusMapeados['Licitando']->id,
            'conditions' => [
                [
                    'field_id' => $camposMapeados['aprovacao']->id,
                    'operator' => RuleOperatorEnum::EQUALS->value,
                    'value' => 'Não aprovado',
                ],
            ],
        ]);

        // 8.5 Adquirindo -> Quitando
        StatusTransition::create([
            'entity_id' => $processo->id,
            'from_status_id' => $statusMapeados['Adquirindo']->id,
            'to_status_id' => $statusMapeados['Quitando']->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allow_return' => true,
            'allowed_role_ids' => [$rolesMapeados['Adquirente']->id],
            'display_order' => 5,
        ]);

        // 8.6 Quitando -> Encerrada
        StatusTransition::create([
            'entity_id' => $processo->id,
            'from_status_id' => $statusMapeados['Quitando']->id,
            'to_status_id' => $statusEncerrada->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allow_return' => true,
            'allowed_role_ids' => [$rolesMapeados['Quitador']->id],
            'display_order' => 6,
        ]);

        // -------------------------------------------------------------
        // 9. DEMANDA INICIAL DE REFERÊNCIA (Documentada na Pág. 4)
        // -------------------------------------------------------------
        Demand::where('entity_id', $processo->id)->delete();

        Demand::create([
            'entity_id' => $processo->id,
            'process_status_id' => $statusNova->id,
            'client_id' => $clienteInct->id,
            'project_id' => $projeto->id,
            'requested_by' => $admin->id,
            'assigned_to' => $admin->id,
            'created_by' => $admin->id,
            'title' => 'Aquisição de coffee break para a inauguração do bazar',
            'status' => DemandStatusEnum::ACTIVE,
            'priority' => DemandPriorityEnum::MEDIUM,
            'sla_due_at' => now()->addHours(120),
            'started_at' => now(),
        ]);
    }
}
