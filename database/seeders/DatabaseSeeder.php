<?php

namespace Database\Seeders;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\RuleOperatorEnum;
use App\Enums\SatisfactionRatingEnum;
use App\Enums\UserRoleEnum;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\Supplier;
use App\Models\TransitionRule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed do banco com cenário corporativo completo do Gaspar:
     * - Usuários com papéis de sistema (Admin, Gestor, Executor, Solicitante)
     * - Papéis de processo (Aprovador, Comprador, Fiscal de Contrato)
     * - Clientes, Fornecedor e Projeto
     * - Macroprocesso e Processo com campos customizados diversificados
     * - Situações nativas e customizadas
     * - Matriz de Permissões de Campos (Oculto, Somente Leitura, Obrigatório, Opcional)
     * - Transição Condicional DMN com Regras de Alçada por Valor (> R$ 10.000) e Urgência
     * - Demandas reais em cada etapa do fluxo para teste imediato
     */
    public function run(): void
    {
        // -------------------------------------------------------------
        // 1. USUÁRIOS DO SISTEMA
        // -------------------------------------------------------------
        $admin = User::updateOrCreate(
            ['email' => 'admin@gaspar.com'],
            [
                'name'      => 'Administrador Gaspar',
                'password'  => Hash::make('admin'),
                'user_role' => UserRoleEnum::ADMIN,
                'phone'     => '(11) 98888-0001',
                'is_active' => true,
            ]
        );

        $gerente = User::updateOrCreate(
            ['email' => 'gerente@gaspar.com'],
            [
                'name'      => 'Carlos Gerente (Aprovador)',
                'password'  => Hash::make('admin'),
                'user_role' => UserRoleEnum::GESTOR,
                'phone'     => '(11) 98888-0002',
                'is_active' => true,
            ]
        );

        $analista = User::updateOrCreate(
            ['email' => 'analista@gaspar.com'],
            [
                'name'      => 'Mariana Analista (Compradora)',
                'password'  => Hash::make('admin'),
                'user_role' => UserRoleEnum::EXECUTOR,
                'phone'     => '(11) 98888-0003',
                'is_active' => true,
            ]
        );

        $solicitante = User::updateOrCreate(
            ['email' => 'solicitante@gaspar.com'],
            [
                'name'      => 'Lucas Solicitante (Demandante)',
                'password'  => Hash::make('admin'),
                'user_role' => UserRoleEnum::VIEWER,
                'phone'     => '(11) 98888-0004',
                'is_active' => true,
            ]
        );

        // -------------------------------------------------------------
        // 2. PAPÉIS DE PROCESSO (Process Roles / Perfis)
        // -------------------------------------------------------------
        $roleAprovador = ProcessRole::firstOrCreate(
            ['name' => 'Aprovador'],
            [
                'color'       => 'blue',
                'description' => 'Responsável pela análise técnica e aprovação de alçada da diretoria.',
                'is_active'   => true,
                'created_by'  => $admin->id,
            ]
        );

        $roleComprador = ProcessRole::firstOrCreate(
            ['name' => 'Comprador'],
            [
                'color'       => 'orange',
                'description' => 'Responsável pela coleta de orçamentos, negociação e fechamento de pedidos.',
                'is_active'   => true,
                'created_by'  => $admin->id,
            ]
        );

        $roleFiscal = ProcessRole::firstOrCreate(
            ['name' => 'Fiscal de Contrato'],
            [
                'color'       => 'green',
                'description' => 'Responsável pelo recebimento físico, conferência e atesto da entrega.',
                'is_active'   => true,
                'created_by'  => $admin->id,
            ]
        );

        // -------------------------------------------------------------
        // 3. CLIENTES, FORNECEDORES E PROJETO
        // -------------------------------------------------------------
        $cliente1 = Client::firstOrCreate(
            ['name' => 'Universidade Federal de Tecnologia - UFTech'],
            [
                'email'     => 'suprimentos@uftech.edu.br',
                'phone'     => '(11) 3222-1000',
                'is_active' => true,
            ]
        );

        $cliente2 = Client::firstOrCreate(
            ['name' => 'Fundação de Apoio à Pesquisa e Inovação - FAPI'],
            [
                'email'     => 'projetos@fapi.org.br',
                'phone'     => '(11) 3222-2000',
                'is_active' => true,
            ]
        );

        $fornecedor = Supplier::firstOrCreate(
            ['name' => 'TechSupply Equipamentos Científicos Ltda'],
            [
                'email'       => 'contato@techsupply.com.br',
                'phone'       => '(11) 4004-9988',
                'business'    => 'Comércio de Equipamentos e TI',
                'description' => 'Fornecedor homologado para licitações e compras diretas.',
            ]
        );

        $projeto = Project::firstOrCreate(
            ['number' => 'PRJ-2026-001'],
            [
                'name'                => 'Modernização dos Laboratórios de Engenharia e IA',
                'description'         => 'Projeto de infraestrutura tecnológica e novos equipamentos para centros de pesquisa.',
                'client_id'           => $cliente1->id,
                'is_active'           => true,
                'start_date_forecast' => now()->startOfYear(),
                'end_date_forecast'   => now()->endOfYear(),
                'budget'              => 500000.00,
                'spent'               => 128500.00,
                'manager_name'        => 'Carlos Gerente',
                'contact_name'        => 'Prof. Dr. Ricardo Silva',
            ]
        );

        // -------------------------------------------------------------
        // 4. MACROPROCESSO
        // -------------------------------------------------------------
        $macroprocesso = Macroprocess::firstOrCreate(
            ['acronym' => 'ADM'],
            [
                'name'                 => 'Gestão Administrativa e Suprimentos',
                'description'          => 'Macroprocesso que engloba aquisições, compras, patrimônio e contratos institucionais.',
                'value_chain_function' => 'Gestão de Recursos',
                'is_active'            => true,
                'created_by'           => $admin->id,
            ]
        );

        // -------------------------------------------------------------
        // 5. PROCESSO (CustomEntity)
        // -------------------------------------------------------------
        $processo = CustomEntity::firstOrCreate(
            ['name' => 'Solicitação de Compras e Aquisições'],
            [
                'macroprocess_id' => $macroprocesso->id,
                'description'     => 'Fluxo corporativo de compras com análise técnica, alçada por valor e cotações.',
                'purpose'         => 'Garantir compras rápidas, transparentes e com aprovação de alçada corporativa.',
                'sla_hours'       => 72,
                'is_active'       => true,
                'created_by'      => $admin->id,
            ]
        );

        // -------------------------------------------------------------
        // 6. CAMPOS CUSTOMIZADOS DO PROCESSO
        // -------------------------------------------------------------
        $camposDefinidos = [
            [
                'name'          => 'Descrição do Item / Serviço',
                'key'           => 'descricao_item',
                'field_type'    => FieldTypeEnum::TEXTAREA,
                'placeholder'   => 'Descreva detalhadamente o item ou serviço requisitado...',
                'is_required'   => true,
                'default_value' => null,
                'options_json'  => null,
            ],
            [
                'name'          => 'Valor Estimado (R$)',
                'key'           => 'valor_estimado',
                'field_type'    => FieldTypeEnum::NUMBER,
                'placeholder'   => 'Ex: 15000.00',
                'is_required'   => true,
                'default_value' => null,
                'options_json'  => null,
            ],
            [
                'name'          => 'Centro de Custo',
                'key'           => 'centro_custo',
                'field_type'    => FieldTypeEnum::SELECT,
                'placeholder'   => 'Selecione o centro de custo',
                'is_required'   => true,
                'default_value' => 'Administração Geral',
                'options_json'  => ['Ensino & Graduação', 'Laboratórios de Pesquisa', 'Administração Geral', 'Projetos de Extensão'],
            ],
            [
                'name'          => 'Grau de Urgência',
                'key'           => 'grau_urgencia',
                'field_type'    => FieldTypeEnum::RADIO,
                'placeholder'   => null,
                'is_required'   => true,
                'default_value' => 'Normal',
                'options_json'  => ['Baixa', 'Normal', 'Urgente', 'Emergencial'],
            ],
            [
                'name'          => 'Justificativa da Necessidade',
                'key'           => 'justificativa',
                'field_type'    => FieldTypeEnum::TEXT,
                'placeholder'   => 'Explique a motivação desta compra...',
                'is_required'   => false,
                'default_value' => null,
                'options_json'  => null,
            ],
            [
                'name'          => 'Parecer da Diretoria Executiva',
                'key'           => 'parecer_diretoria',
                'field_type'    => FieldTypeEnum::TEXTAREA,
                'placeholder'   => 'Parecer formal e dotação orçamentária aprovada pela diretoria...',
                'is_required'   => false,
                'default_value' => null,
                'options_json'  => null,
            ],
            [
                'name'          => 'Fornecedor Vencedor / CNPJ',
                'key'           => 'fornecedor_vencedor',
                'field_type'    => FieldTypeEnum::TEXT,
                'placeholder'   => 'Razão Social ou CNPJ da cotação selecionada...',
                'is_required'   => false,
                'default_value' => null,
                'options_json'  => null,
            ],
        ];

        $camposMapeados = [];
        $fieldOrder = 1;
        foreach ($camposDefinidos as $dadoCampo) {
            $campo = CustomField::updateOrCreate(
                ['key' => $dadoCampo['key']],
                array_merge($dadoCampo, ['created_by' => $admin->id])
            );
            $camposMapeados[$dadoCampo['key']] = $campo;

            if (! $processo->fields()->where('custom_field_id', $campo->id)->exists()) {
                $processo->fields()->attach($campo->id, ['field_order' => $fieldOrder++]);
            }
        }

        // -------------------------------------------------------------
        // 7. SITUAÇÕES DO PROCESSO (Process Statuses)
        // -------------------------------------------------------------
        $statusNova        = ProcessStatus::where('system_key', 'new')->first() ?? ProcessStatus::where('name', 'Nova')->first();
        $statusEncerrada   = ProcessStatus::where('system_key', 'closed')->first() ?? ProcessStatus::where('name', 'Encerrada')->first();
        $statusAvaliada    = ProcessStatus::where('system_key', 'evaluated')->first() ?? ProcessStatus::where('name', 'Avaliada')->first();
        $statusCancelada   = ProcessStatus::where('system_key', 'canceled')->first() ?? ProcessStatus::where('name', 'Cancelada')->first();
        $statusCondicional = ProcessStatus::where('system_key', 'conditional')->first();

        $statusAnalise = ProcessStatus::firstOrCreate(
            ['name' => 'Em Análise Técnica'],
            [
                'color'      => 'orange',
                'is_system'  => false,
                'system_key' => null,
                'created_by' => $admin->id,
            ]
        );

        $statusDiretoria = ProcessStatus::firstOrCreate(
            ['name' => 'Aguardando Diretoria'],
            [
                'color'      => 'purple',
                'is_system'  => false,
                'system_key' => null,
                'created_by' => $admin->id,
            ]
        );

        $statusCotacao = ProcessStatus::firstOrCreate(
            ['name' => 'Em Cotação de Preços'],
            [
                'color'      => 'blue',
                'is_system'  => false,
                'system_key' => null,
                'created_by' => $admin->id,
            ]
        );

        // Vincula as situações customizadas ao processo na ordem correta
        $statusCustomizados = [
            ['id' => $statusAnalise->id, 'order' => 2],
            ['id' => $statusDiretoria->id, 'order' => 3],
            ['id' => $statusCotacao->id, 'order' => 4],
        ];

        foreach ($statusCustomizados as $customStatus) {
            if (! $processo->processStatuses()->where('process_status_id', $customStatus['id'])->exists()) {
                $processo->processStatuses()->attach($customStatus['id'], ['display_order' => $customStatus['order']]);
            }
        }

        // -------------------------------------------------------------
        // 8. MEMBROS DO PROCESSO
        // -------------------------------------------------------------
        ProcessMember::updateOrCreate(
            [
                'entity_id' => $processo->id,
                'user_id'   => $gerente->id,
            ],
            [
                'process_role_id' => $roleAprovador->id,
            ]
        );

        ProcessMember::updateOrCreate(
            [
                'entity_id' => $processo->id,
                'user_id'   => $analista->id,
            ],
            [
                'process_role_id' => $roleComprador->id,
            ]
        );

        // -------------------------------------------------------------
        // 9. MATRIZ DE PERMISSÕES DE CAMPOS (FieldPermission)
        // -------------------------------------------------------------
        // 9.1 Campo "Parecer da Diretoria Executiva"
        // - Em "Nova" e "Em Análise": OCULTO (HIDDEN)
        // - Em "Aguardando Diretoria": OBRIGATÓRIO (REQUIRED)
        // - Em "Em Cotação de Preços" e "Encerrada": SOMENTE LEITURA (READONLY)
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['parecer_diretoria']->id, 'process_status_id' => $statusNova->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::HIDDEN]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['parecer_diretoria']->id, 'process_status_id' => $statusAnalise->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::HIDDEN]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['parecer_diretoria']->id, 'process_status_id' => $statusDiretoria->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::REQUIRED]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['parecer_diretoria']->id, 'process_status_id' => $statusCotacao->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::READONLY]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['parecer_diretoria']->id, 'process_status_id' => $statusEncerrada->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::READONLY]
        );

        // 9.2 Campo "Fornecedor Vencedor / CNPJ"
        // - Oculto até a fase de cotação
        // - Obrigatório na fase de cotação
        // - Somente leitura após encerrado
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['fornecedor_vencedor']->id, 'process_status_id' => $statusNova->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::HIDDEN]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['fornecedor_vencedor']->id, 'process_status_id' => $statusAnalise->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::HIDDEN]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['fornecedor_vencedor']->id, 'process_status_id' => $statusDiretoria->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::HIDDEN]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['fornecedor_vencedor']->id, 'process_status_id' => $statusCotacao->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::REQUIRED]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['fornecedor_vencedor']->id, 'process_status_id' => $statusEncerrada->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::READONLY]
        );

        // 9.3 Campo "Valor Estimado"
        // - Obrigatório ao criar (Nova)
        // - Travado (READONLY) nas etapas seguintes para preservar o valor solicitado
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'process_status_id' => $statusNova->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::REQUIRED]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'process_status_id' => $statusCotacao->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::READONLY]
        );
        FieldPermission::updateOrCreate(
            ['entity_id' => $processo->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'process_status_id' => $statusEncerrada->id, 'process_role_id' => null],
            ['permission' => FieldPermissionEnum::READONLY]
        );

        // -------------------------------------------------------------
        // 10. TRANSIÇÕES DE STATUS E REGRAS CONDICIONAIS (DMN)
        // -------------------------------------------------------------
        // Limpa transições antigas do processo para garantir integridade
        StatusTransition::where('entity_id', $processo->id)->delete();

        // 10.1 Nova -> Em Análise Técnica
        StatusTransition::create([
            'entity_id'        => $processo->id,
            'from_status_id'   => $statusNova->id,
            'to_status_id'     => $statusAnalise->id,
            'label'            => 'Iniciar Análise Técnica',
            'allow_return'     => true,
            'allowed_role_ids' => [$roleAprovador->id, '__assignee__'],
            'display_order'    => 1,
        ]);

        // 10.2 Em Análise Técnica -> Condicional (COM TABELA DE DECISÃO DMN)
        // Regra 1: Se Valor Estimado > 10.000 -> Aguardando Diretoria
        // Regra 2: Se Grau de Urgência = 'Emergencial' -> Aguardando Diretoria
        // Senão: Em Cotação de Preços (default_to_status_id)
        $transicaoCondicional = StatusTransition::create([
            'entity_id'            => $processo->id,
            'from_status_id'       => $statusAnalise->id,
            'to_status_id'         => $statusCondicional->id,
            'default_to_status_id' => $statusCotacao->id,
            'label'                => 'Encaminhar Solicitação',
            'allow_return'         => true,
            'allowed_role_ids'     => [$roleAprovador->id, '__assignee__'],
            'display_order'        => 2,
        ]);

        TransitionRule::create([
            'status_transition_id' => $transicaoCondicional->id,
            'rule_order'           => 0,
            'conditions'           => [
                [
                    'field_id' => $camposMapeados['valor_estimado']->id,
                    'operator' => RuleOperatorEnum::GREATER->value,
                    'value'    => '10000',
                ],
            ],
            'to_status_id'         => $statusDiretoria->id,
        ]);

        TransitionRule::create([
            'status_transition_id' => $transicaoCondicional->id,
            'rule_order'           => 1,
            'conditions'           => [
                [
                    'field_id' => $camposMapeados['grau_urgencia']->id,
                    'operator' => RuleOperatorEnum::EQUALS->value,
                    'value'    => 'Emergencial',
                ],
            ],
            'to_status_id'         => $statusDiretoria->id,
        ]);

        // 10.3 Aguardando Diretoria -> Em Cotação de Preços (Aprovado pela Diretoria)
        StatusTransition::create([
            'entity_id'        => $processo->id,
            'from_status_id'   => $statusDiretoria->id,
            'to_status_id'     => $statusCotacao->id,
            'label'            => 'Aprovar Diretoria (Liberar Cotação)',
            'allow_return'     => true,
            'allowed_role_ids' => [$roleAprovador->id],
            'display_order'    => 3,
        ]);

        // 10.4 Em Cotação de Preços -> Encerrada (Compra Concluída)
        StatusTransition::create([
            'entity_id'        => $processo->id,
            'from_status_id'   => $statusCotacao->id,
            'to_status_id'     => $statusEncerrada->id,
            'label'            => 'Concluir Compra e Entrega',
            'allow_return'     => false,
            'allowed_role_ids' => [$roleComprador->id, $roleAprovador->id],
            'display_order'    => 4,
        ]);

        // -------------------------------------------------------------
        // 11. DEMANDAS REAIS PRÉ-POPULADAS PARA TESTE IMEDIATO
        // -------------------------------------------------------------
        Demand::where('entity_id', $processo->id)->delete();

        // DEMANDA 1: Nova (Cadeiras Ergonômicas - R$ 3.500)
        $d1 = Demand::create([
            'entity_id'         => $processo->id,
            'process_status_id' => $statusNova->id,
            'client_id'         => $cliente1->id,
            'project_id'        => $projeto->id,
            'requested_by'      => $solicitante->id,
            'assigned_to'       => null,
            'created_by'        => $solicitante->id,
            'title'             => 'Aquisição de 5 Cadeiras Ergonômicas NR-17',
            'description'       => 'Substituição de cadeiras desgastadas no setor de atendimento e secretaria.',
            'status'            => DemandStatusEnum::ACTIVE,
            'priority'          => DemandPriorityEnum::MEDIUM,
            'sla_due_at'        => now()->addDays(3),
        ]);
        DemandFieldValue::create(['demand_id' => $d1->id, 'custom_field_id' => $camposMapeados['descricao_item']->id, 'value' => 'Cadeiras giratórias com regulagem de braço e lombar em conformidade com a NR-17.']);
        DemandFieldValue::create(['demand_id' => $d1->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'value' => '3500.00']);
        DemandFieldValue::create(['demand_id' => $d1->id, 'custom_field_id' => $camposMapeados['centro_custo']->id, 'value' => 'Administração Geral']);
        DemandFieldValue::create(['demand_id' => $d1->id, 'custom_field_id' => $camposMapeados['grau_urgencia']->id, 'value' => 'Normal']);
        DemandFieldValue::create(['demand_id' => $d1->id, 'custom_field_id' => $camposMapeados['justificativa']->id, 'value' => 'Melhoria ergonômica para a equipe de atendimento ao público.']);

        // DEMANDA 2: Em Análise Técnica - ALTO VALOR (R$ 58.000 > 10.000)
        // -> TESTE DA CONDICIONAL: Ao clicar em "Encaminhar", deve ir para "Aguardando Diretoria"!
        $d2 = Demand::create([
            'entity_id'         => $processo->id,
            'process_status_id' => $statusAnalise->id,
            'client_id'         => $cliente1->id,
            'project_id'        => $projeto->id,
            'requested_by'      => $solicitante->id,
            'assigned_to'       => $gerente->id,
            'created_by'        => $solicitante->id,
            'title'             => 'Servidor Rack Dell PowerEdge R760 para Laboratório de IA',
            'description'       => 'Servidor de alta capacidade para processamento de modelos de IA e banco de dados acadêmico.',
            'status'            => DemandStatusEnum::ACTIVE,
            'priority'          => DemandPriorityEnum::HIGH,
            'started_at'        => now()->subHours(5),
            'sla_due_at'        => now()->addDays(2),
        ]);
        DemandFieldValue::create(['demand_id' => $d2->id, 'custom_field_id' => $camposMapeados['descricao_item']->id, 'value' => 'Servidor Dell PowerEdge R760 2U, 2x Intel Xeon Gold, 128GB RAM DDR5, 4TB NVMe Enterprise com controladora RAID.']);
        DemandFieldValue::create(['demand_id' => $d2->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'value' => '58000.00']);
        DemandFieldValue::create(['demand_id' => $d2->id, 'custom_field_id' => $camposMapeados['centro_custo']->id, 'value' => 'Laboratórios de Pesquisa']);
        DemandFieldValue::create(['demand_id' => $d2->id, 'custom_field_id' => $camposMapeados['grau_urgencia']->id, 'value' => 'Normal']);
        DemandFieldValue::create(['demand_id' => $d2->id, 'custom_field_id' => $camposMapeados['justificativa']->id, 'value' => 'Infraestrutura essencial aprovada no termo de fomento do projeto de pesquisa.']);

        // Registra log para habilitar o botão de retorno
        ActivityLog::create([
            'user_id'        => $gerente->id,
            'event'          => 'transition',
            'auditable_type' => Demand::class,
            'auditable_id'   => (string) $d2->id,
            'old_values'     => ['process_status_id' => $statusNova->id, 'status_name' => 'Nova'],
            'new_values'     => ['process_status_id' => $statusAnalise->id, 'status_name' => 'Em Análise Técnica', 'action' => 'Iniciar Análise Técnica'],
            'created_at'     => now()->subHours(5),
        ]);

        // DEMANDA 3: Em Análise Técnica - BAIXO VALOR, MAS EMERGENCIAL (R$ 4.200 + Emergencial)
        // -> TESTE DA CONDICIONAL (Regra 2): Deve ir para "Aguardando Diretoria" mesmo com valor baixo!
        $d3 = Demand::create([
            'entity_id'         => $processo->id,
            'process_status_id' => $statusAnalise->id,
            'client_id'         => $cliente1->id,
            'project_id'        => null,
            'requested_by'      => $solicitante->id,
            'assigned_to'       => $gerente->id,
            'created_by'        => $solicitante->id,
            'title'             => 'Reparo Emergencial do Link Troncal de Fibra Óptica',
            'description'       => 'Rompimento de cabeamento subterrâneo entre blocos A e C interrompendo conexões.',
            'status'            => DemandStatusEnum::ACTIVE,
            'priority'          => DemandPriorityEnum::URGENT,
            'started_at'        => now()->subHours(2),
            'sla_due_at'        => now()->addHours(12),
        ]);
        DemandFieldValue::create(['demand_id' => $d3->id, 'custom_field_id' => $camposMapeados['descricao_item']->id, 'value' => 'Fusão de fibra óptica monomodo e substituição de 80 metros de cabo óptico auto-sustentado.']);
        DemandFieldValue::create(['demand_id' => $d3->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'value' => '4200.00']);
        DemandFieldValue::create(['demand_id' => $d3->id, 'custom_field_id' => $camposMapeados['centro_custo']->id, 'value' => 'Administração Geral']);
        DemandFieldValue::create(['demand_id' => $d3->id, 'custom_field_id' => $camposMapeados['grau_urgencia']->id, 'value' => 'Emergencial']);
        DemandFieldValue::create(['demand_id' => $d3->id, 'custom_field_id' => $camposMapeados['justificativa']->id, 'value' => 'Situação crítica com perda de acesso aos sistemas acadêmicos e e-mails.']);

        // DEMANDA 4: Em Análise Técnica - BAIXO VALOR E NORMAL (R$ 1.850 e Normal)
        // -> TESTE DO SENÃO (DEFAULT): Deve pular a diretoria e ir direto para "Em Cotação de Preços"!
        $d4 = Demand::create([
            'entity_id'         => $processo->id,
            'process_status_id' => $statusAnalise->id,
            'client_id'         => $cliente2->id,
            'project_id'        => null,
            'requested_by'      => $solicitante->id,
            'assigned_to'       => $gerente->id,
            'created_by'        => $solicitante->id,
            'title'             => 'Resmas de Papel Sulfite A4 e Canetas para o Semestre',
            'description'       => 'Suprimento de papelaria para secretarias acadêmicas e coordenações de curso.',
            'status'            => DemandStatusEnum::ACTIVE,
            'priority'          => DemandPriorityEnum::LOW,
            'started_at'        => now()->subHour(),
            'sla_due_at'        => now()->addDays(5),
        ]);
        DemandFieldValue::create(['demand_id' => $d4->id, 'custom_field_id' => $camposMapeados['descricao_item']->id, 'value' => '50 caixas de papel sulfite A4 75g (Chamex/Report) e 200 canetas esferográficas azuis.']);
        DemandFieldValue::create(['demand_id' => $d4->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'value' => '1850.00']);
        DemandFieldValue::create(['demand_id' => $d4->id, 'custom_field_id' => $camposMapeados['centro_custo']->id, 'value' => 'Ensino & Graduação']);
        DemandFieldValue::create(['demand_id' => $d4->id, 'custom_field_id' => $camposMapeados['grau_urgencia']->id, 'value' => 'Normal']);
        DemandFieldValue::create(['demand_id' => $d4->id, 'custom_field_id' => $camposMapeados['justificativa']->id, 'value' => 'Reposição periódica de almoxarifado.']);

        // DEMANDA 5: Concluída e Avaliada (Microscópio Óptico - R$ 24.500)
        // -> TESTE DE AVALIAÇÃO DE SATISFAÇÃO (CSAT) E RELATÓRIOS
        $d5 = Demand::create([
            'entity_id'                 => $processo->id,
            'process_status_id'         => $statusAvaliada->id,
            'client_id'                 => $cliente1->id,
            'project_id'                => $projeto->id,
            'requested_by'              => $solicitante->id,
            'assigned_to'               => $analista->id,
            'created_by'                => $solicitante->id,
            'title'                     => 'Aquisição de Microscópio Óptico Trinocular de Alta Resolução',
            'description'               => 'Microscópio biológico avançado para os laboratórios de patologia e biologia celular.',
            'status'                    => DemandStatusEnum::COMPLETED,
            'priority'                  => DemandPriorityEnum::HIGH,
            'started_at'                => now()->subDays(8),
            'completed_at'              => now()->subDays(1),
            'satisfaction_rating'       => SatisfactionRatingEnum::GREAT,
            'satisfaction_comment'      => 'Equipamento excelente, entregue devidamente calibrado antes do prazo estipulado. Atendimento primoroso!',
            'satisfaction_evaluated_at' => now()->subHours(12),
        ]);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['descricao_item']->id, 'value' => 'Microscópio trinocular com câmera digital 4K acoplada e lentes plano-acromáticas.']);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['valor_estimado']->id, 'value' => '24500.00']);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['centro_custo']->id, 'value' => 'Laboratórios de Pesquisa']);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['grau_urgencia']->id, 'value' => 'Normal']);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['justificativa']->id, 'value' => 'Aprovado pelo edital de modernização de laboratórios FAPI/UFTech.']);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['parecer_diretoria']->id, 'value' => 'Aprovado pela Diretoria com recursos da rubrica de capital do projeto PRJ-2026-001.']);
        DemandFieldValue::create(['demand_id' => $d5->id, 'custom_field_id' => $camposMapeados['fornecedor_vencedor']->id, 'value' => 'TechSupply Equipamentos Científicos Ltda (CNPJ 12.345.678/0001-90)']);
    }
}
