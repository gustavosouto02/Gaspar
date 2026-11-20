<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\DemandTransitionException;
use App\Filament\Pages\DemandReports;
use App\Filament\Resources\CustomRecordResource\Pages\EditCustomRecord;
use App\Filament\Resources\CustomRecordResource\Pages\ListCustomRecords;
use App\Filament\Resources\CustomRecordResource\Pages\ViewCustomRecord;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Services\DemandTransitionService;
use App\Services\MoneyFieldVisibility;
use App\Support\MoneyInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T2 — campos de valor (CURRENCY) só aparecem em consultas e relatórios (relatório
 * de demandas, PDF, busca e listagem dos cadastros) para ADMIN e para o gestor do
 * dono do campo: o Processo (e o gestor do seu Macroprocesso) ou o Cadastro.
 * Formulários e telas de edição não são restritos.
 */
class MoneyFieldVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gestorProcesso;

    private User $gestorMacro;

    private User $gestorOutro;

    private User $executor;

    private CustomEntity $processo;

    private CustomField $campoValor;

    private CustomField $campoTexto;

    private ProcessStatus $situacao;

    private Demand $demanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario(UserRoleEnum::ADMIN, 'admin');
        $this->gestorProcesso = $this->usuario(UserRoleEnum::GESTOR, 'gestor-processo');
        $this->gestorMacro = $this->usuario(UserRoleEnum::GESTOR, 'gestor-macro');
        $this->gestorOutro = $this->usuario(UserRoleEnum::GESTOR, 'gestor-outro');
        $this->executor = $this->usuario(UserRoleEnum::EXECUTOR, 'executor');

        $macro = Macroprocess::create([
            'name' => 'Macro Valores',
            'acronym' => 'MVA',
            'value_chain_function' => 'Suportar Processos',
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
            'manager_id' => $this->gestorMacro->id,
        ]);

        $this->processo = CustomEntity::create([
            'name' => 'Processo Valores',
            'description' => 'Processo com campo de valor',
            'is_active' => true,
            'sla_hours' => 24,
            'macroprocess_id' => $macro->id,
            'manager_id' => $this->gestorProcesso->id,
            'created_by' => $this->admin->id,
        ]);

        $this->campoValor = CustomField::factory()->create([
            'name' => 'Valor estimado',
            'key' => 'valor_estimado',
            'field_type' => FieldTypeEnum::CURRENCY,
        ]);
        $this->campoTexto = CustomField::factory()->create([
            'name' => 'Observação',
            'key' => 'observacao',
            'field_type' => FieldTypeEnum::TEXT,
        ]);
        $this->processo->fields()->attach($this->campoValor->id, ['field_order' => 0]);
        $this->processo->fields()->attach($this->campoTexto->id, ['field_order' => 1]);

        $this->situacao = $this->processo->processStatuses()->where('system_key', 'new')->firstOrFail();

        // Transição sem restrição de papel: quem é demandante, responsável ou membro
        // do processo pode editar a demanda (DemandPolicy::update)
        StatusTransition::create([
            'entity_id' => $this->processo->id,
            'from_status_id' => $this->situacao->id,
            'to_status_id' => $this->processo->processStatuses()->where('system_key', 'closed')->firstOrFail()->id,
            'label' => DemandButtonEnum::cases()[0]->value,
            'allowed_role_ids' => [],
            'display_order' => 0,
        ]);

        // Os gestores do processo e do macro participam do processo (editam a demanda)
        $papelGestor = ProcessRole::factory()->create();
        foreach ([$this->gestorProcesso, $this->gestorMacro] as $membro) {
            $this->membro($membro, $papelGestor);
        }

        // Matriz: todos os campos Opcional (o campo de valor não é restrito no formulário)
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL);
        $this->permissao($this->campoTexto, FieldPermissionEnum::OPTIONAL);

        $this->demanda = Demand::factory()->create([
            'title' => 'Compra de equipamento',
            'entity_id' => $this->processo->id,
            'client_id' => Client::create(['name' => 'Cliente Teste', 'is_active' => true])->id,
            'process_status_id' => $this->situacao->id,
            'requested_by' => $this->executor->id,
            'created_by' => $this->executor->id,
            'assigned_to' => $this->executor->id,
            'status' => DemandStatusEnum::ACTIVE,
            'parent_demand_id' => null,
        ]);

        DemandFieldValue::create([
            'demand_id' => $this->demanda->id,
            'custom_field_id' => $this->campoValor->id,
            'value' => '1500.50',
        ]);
        DemandFieldValue::create([
            'demand_id' => $this->demanda->id,
            'custom_field_id' => $this->campoTexto->id,
            'value' => 'texto original',
        ]);
    }

    private function membro(User $usuario, ProcessRole $papel): void
    {
        ProcessMember::create([
            'entity_id' => $this->processo->id,
            'user_id' => $usuario->id,
            'process_role_id' => $papel->id,
        ]);
    }

    private function permissao(CustomField $campo, FieldPermissionEnum $permissao, ?ProcessRole $papel = null): void
    {
        FieldPermission::updateOrCreate([
            'entity_id' => $this->processo->id,
            'custom_field_id' => $campo->id,
            'process_status_id' => $this->situacao->id,
            'process_role_id' => $papel?->id,
        ], ['permission' => $permissao]);
    }

    private function usuario(UserRoleEnum $papel, string $nome): User
    {
        return User::factory()->create([
            'name' => $nome,
            'user_role' => $papel,
            'is_active' => true,
        ]);
    }

    private function valorGravado(): ?string
    {
        return DemandFieldValue::where('demand_id', $this->demanda->id)
            ->where('custom_field_id', $this->campoValor->id)
            ->value('value');
    }

    // ------------------------------------------------------------------
    // Regra central
    // ------------------------------------------------------------------

    public function test_regra_do_processo_admin_gestor_do_processo_e_gestor_do_macroprocesso_veem(): void
    {
        $regra = app(MoneyFieldVisibility::class);

        $this->assertTrue($regra->canView($this->admin, $this->processo));
        $this->assertTrue($regra->canView($this->gestorProcesso, $this->processo));
        $this->assertTrue($regra->canView($this->gestorMacro, $this->processo));
    }

    public function test_regra_do_processo_gestor_de_outro_processo_executor_e_anonimo_nao_veem(): void
    {
        $regra = app(MoneyFieldVisibility::class);

        $this->assertFalse($regra->canView($this->gestorOutro, $this->processo));
        $this->assertFalse($regra->canView($this->executor, $this->processo));
        $this->assertFalse($regra->canView(null, $this->processo));
    }

    public function test_regra_do_cadastro_qualquer_um_dos_gestores_ve(): void
    {
        $cadastro = CustomRecordType::create(['name' => 'Equipamentos', 'slug' => 'equipamentos', 'is_system' => false]);
        $cadastro->managers()->attach([$this->gestorProcesso->id, $this->gestorOutro->id]);
        $regra = app(MoneyFieldVisibility::class);

        $this->assertTrue($regra->canView($this->admin, $cadastro));
        $this->assertTrue($regra->canView($this->gestorProcesso, $cadastro));
        $this->assertTrue($regra->canView($this->gestorOutro, $cadastro));
        $this->assertFalse($regra->canView($this->gestorMacro, $cadastro));
        $this->assertFalse($regra->canView($this->executor, $cadastro));
    }

    public function test_ids_de_processos_visiveis_por_perfil(): void
    {
        $regra = app(MoneyFieldVisibility::class);

        $this->assertNull($regra->viewableEntityIds($this->admin));
        $this->assertSame([$this->processo->id], $regra->viewableEntityIds($this->gestorProcesso));
        $this->assertSame([$this->processo->id], $regra->viewableEntityIds($this->gestorMacro));
        $this->assertSame([], $regra->viewableEntityIds($this->gestorOutro));
        $this->assertSame([], $regra->viewableEntityIds(null));
    }

    // ------------------------------------------------------------------
    // Edição da demanda (formulário não é restrito)
    // ------------------------------------------------------------------

    public function test_gestor_do_processo_edita_o_valor_normalmente(): void
    {
        $this->actingAs($this->gestorProcesso);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.valor_estimado', '2000')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(2000, $this->valorGravado());
    }

    // ------------------------------------------------------------------
    // Formato do campo de valor (1.000,00)
    // ------------------------------------------------------------------

    public function test_campo_de_valor_abre_formatado_e_a_tela_de_edicao_mostra_o_padrao_brasileiro(): void
    {
        $this->actingAs($this->gestorProcesso);

        $componente = Livewire::test(EditDemand::class, ['record' => $this->demanda->id]);

        $this->assertSame('1.500,50', $componente->get('data.field_data.valor_estimado'));
    }

    public function test_salva_o_valor_digitado_no_padrao_brasileiro_como_numero_puro(): void
    {
        $this->actingAs($this->gestorProcesso);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.valor_estimado', '2.000,5')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2000.50', $this->valorGravado());
    }

    public function test_ao_sair_do_campo_completa_as_casas_decimais(): void
    {
        $this->actingAs($this->gestorProcesso);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.valor_estimado', '1000')
            ->assertSet('data.field_data.valor_estimado', '1.000,00');
    }

    public function test_valor_invalido_e_rejeitado_e_nao_altera_o_gravado(): void
    {
        $this->actingAs($this->gestorProcesso);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.valor_estimado', 'abc')
            ->call('save')
            ->assertHasFormErrors(['field_data.valor_estimado']);

        $this->assertSame('1500.50', $this->valorGravado());
    }

    public function test_formatos_de_entrada_e_de_armazenamento(): void
    {
        $casos = [
            ['1.234,56', '1234.56', '1.234,56'],
            ['1.000', '1000.00', '1.000,00'],
            ['1.234.567', '1234567.00', '1.234.567,00'],
            ['1000', '1000.00', '1.000,00'],
            ['1500.5', '1500.50', '1.500,50'],
            ['1500.50', '1500.50', '1.500,50'],
            ['0,5', '0.50', '0,50'],
            ['R$ 2.500,00', '2500.00', '2.500,00'],
            ['-300', '-300.00', '-300,00'],
        ];

        foreach ($casos as [$entrada, $gravado, $exibido]) {
            $this->assertSame($gravado, MoneyInput::normalize($entrada), "normalize({$entrada})");
            $this->assertSame($exibido, MoneyInput::format($entrada), "format({$entrada})");
        }

        $this->assertNull(MoneyInput::normalize(''));
        $this->assertNull(MoneyInput::normalize(null));
        $this->assertNull(MoneyInput::normalize('abc'));
        $this->assertNull(MoneyInput::normalize('1,2,3'));
        $this->assertSame('abc', MoneyInput::format('abc'));
        $this->assertSame('R$ 1.234,50', MoneyInput::display('1234.5'));
        $this->assertSame('—', MoneyInput::display(null));
    }

    // ------------------------------------------------------------------
    // Busca, relatório e PDF
    // ------------------------------------------------------------------

    public function test_busca_nao_encontra_demanda_pelo_valor_de_quem_nao_ve(): void
    {
        $this->actingAs($this->executor);
        $this->assertSame(0, Demand::query()->globalSearch('1500')->count());

        $this->actingAs($this->gestorOutro);
        $this->assertSame(0, Demand::query()->globalSearch('1500')->count());
    }

    public function test_busca_encontra_demanda_pelo_valor_para_gestor_e_admin(): void
    {
        foreach ([$this->gestorProcesso, $this->gestorMacro, $this->admin] as $usuario) {
            $this->actingAs($usuario);
            $this->assertSame(1, Demand::query()->globalSearch('1500')->count(), $usuario->name);
        }
    }

    public function test_busca_continua_encontrando_pelo_texto_e_pelo_nome_do_campo(): void
    {
        $this->actingAs($this->executor);

        $this->assertSame(1, Demand::query()->globalSearch('texto original')->count());
        $this->assertSame(1, Demand::query()->globalSearch('Valor estimado')->count());
    }

    /**
     * Colunas de campos customizados do relatório com o processo filtrado.
     * O segundo ciclo ($refresh) é necessário porque a tabela é montada no boot,
     * antes de o filtro da requisição anterior valer.
     *
     * @return array<string>
     */
    private function colunasDeCamposDoRelatorio(): array
    {
        $componente = Livewire::test(DemandReports::class)
            ->set('tableFilters.entity_id.value', $this->processo->id)
            ->call('$refresh');

        return array_values(array_filter(
            array_keys($componente->instance()->getTable()->getColumns()),
            fn (string $nome) => str_starts_with($nome, 'field_')
        ));
    }

    public function test_relatorio_nao_tem_a_coluna_de_valor_para_quem_nao_ve(): void
    {
        $this->actingAs($this->executor);
        $colunas = $this->colunasDeCamposDoRelatorio();

        $this->assertContains('field_'.$this->campoTexto->id, $colunas);
        $this->assertNotContains('field_'.$this->campoValor->id, $colunas);
    }

    public function test_relatorio_tem_a_coluna_de_valor_para_gestor_e_admin(): void
    {
        foreach ([$this->gestorProcesso, $this->gestorMacro, $this->admin] as $usuario) {
            $this->actingAs($usuario);

            $colunas = $this->colunasDeCamposDoRelatorio();

            $this->assertContains('field_'.$this->campoTexto->id, $colunas, $usuario->name);
            $this->assertContains('field_'.$this->campoValor->id, $colunas, $usuario->name);
        }
    }

    public function test_pdf_omite_o_valor_de_quem_nao_ve_e_mostra_para_quem_ve(): void
    {
        $demanda = Demand::with(['entity.macroprocess', 'fieldValues.customField'])->find($this->demanda->id);
        $dados = [
            'demand' => $demanda,
            'transitionsHistory' => collect(),
        ];

        $semPermissao = view('pdf.demand-report', $dados + ['canViewMoney' => false])->render();
        $this->assertStringNotContainsString('Valor estimado', $semPermissao);
        $this->assertStringNotContainsString('1.500,50', $semPermissao);
        $this->assertStringContainsString('texto original', $semPermissao);

        $comPermissao = view('pdf.demand-report', $dados + ['canViewMoney' => true])->render();
        $this->assertStringContainsString('Valor estimado', $comPermissao);
        $this->assertStringContainsString('R$ 1.500,50', $comPermissao);
    }

    public function test_rota_do_pdf_responde_para_quem_nao_ve_o_valor(): void
    {
        $this->actingAs($this->executor)
            ->get('/demands/'.$this->demanda->id.'/pdf')
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Cadastros dinâmicos (data_json)
    // ------------------------------------------------------------------

    private function cadastroComValor(): array
    {
        $cadastro = CustomRecordType::create(['name' => 'Equipamentos', 'slug' => 'equipamentos', 'is_system' => false]);
        $cadastro->managers()->attach([$this->gestorProcesso->id, $this->gestorOutro->id]);

        $valor = CustomField::factory()->create([
            'name' => 'Preço', 'key' => 'preco_unit', 'field_type' => FieldTypeEnum::CURRENCY,
        ]);
        $nome = CustomField::factory()->create([
            'name' => 'Modelo', 'key' => 'modelo', 'field_type' => FieldTypeEnum::TEXT,
        ]);
        $cadastro->fields()->attach($nome->id, ['field_order' => 0]);
        $cadastro->fields()->attach($valor->id, ['field_order' => 1]);

        $registro = CustomRecord::create([
            'custom_record_type_id' => $cadastro->id,
            'created_by' => $this->admin->id,
            'data_json' => ['modelo' => 'Impressora X', 'preco_unit' => '800'],
        ]);

        return [$cadastro, $registro];
    }

    // ------------------------------------------------------------------
    // Cadastros base (custom_data na própria tabela)
    // ------------------------------------------------------------------

    private function cadastroBaseComValor(): CustomRecordType
    {
        $tipo = CustomRecordType::where('model_class', Client::class)->where('is_system', true)->firstOrFail();
        $tipo->managers()->sync([$this->gestorProcesso->id]);

        $valor = CustomField::factory()->create([
            'name' => 'Contrato anual', 'key' => 'contrato_anual', 'field_type' => FieldTypeEnum::CURRENCY,
        ]);
        $tipo->fields()->attach($valor->id, ['field_order' => 99]);

        return $tipo;
    }

    // ------------------------------------------------------------------
    // Gestor precisa continuar GESTOR e ativo
    // ------------------------------------------------------------------

    /**
     * Muda o usuário direto no banco, sem os eventos do model (que já limpam o vínculo de
     * gestor): garante que a regra de visibilidade também recusa quem ainda consta como gestor.
     */
    private function alterarPorFora(User $usuario, array $atributos): void
    {
        DB::table('users')->where('id', $usuario->id)->update($atributos);
    }

    public function test_gestor_que_deixa_de_ser_gestor_pelo_model_fica_sem_vinculo_e_so_admin_ve(): void
    {
        $regra = app(MoneyFieldVisibility::class);

        $this->gestorProcesso->update(['user_role' => UserRoleEnum::EXECUTOR]);

        $this->assertNull($this->processo->fresh()->manager_id);
        $this->assertFalse($regra->canView($this->gestorProcesso->fresh(), $this->processo->fresh()));
        $this->assertTrue($regra->canView($this->admin, $this->processo->fresh()));
    }

    public function test_gestor_do_processo_que_virou_executor_ou_inativo_deixa_de_ver(): void
    {
        $this->processo->update(['manager_id' => $this->gestorOutro->id]);
        $regra = app(MoneyFieldVisibility::class);

        $this->assertTrue($regra->canView($this->gestorOutro, $this->processo));
        $this->assertSame([$this->processo->id], $regra->viewableEntityIds($this->gestorOutro));

        $this->alterarPorFora($this->gestorOutro, ['user_role' => UserRoleEnum::EXECUTOR->value]);
        $this->assertFalse($regra->canView($this->gestorOutro->fresh(), $this->processo));
        $this->assertSame([], $regra->viewableEntityIds($this->gestorOutro->fresh()));

        $this->alterarPorFora($this->gestorOutro, ['user_role' => UserRoleEnum::GESTOR->value, 'is_active' => false]);
        $this->assertFalse($regra->canView($this->gestorOutro->fresh(), $this->processo));
        $this->assertSame([], $regra->viewableEntityIds($this->gestorOutro->fresh()));
    }

    public function test_ex_gestor_do_processo_nao_acha_a_demanda_pelo_valor_nem_ve_o_valor(): void
    {
        $this->processo->update(['manager_id' => $this->gestorOutro->id]);

        $this->actingAs($this->gestorOutro);
        $this->assertSame(1, Demand::query()->globalSearch('1500')->count());

        $this->alterarPorFora($this->gestorOutro, ['user_role' => UserRoleEnum::EXECUTOR->value]);
        $this->actingAs($this->gestorOutro->fresh());

        $this->assertSame(0, Demand::query()->globalSearch('1500')->count());
        $this->assertNotContains('field_'.$this->campoValor->id, $this->colunasDeCamposDoRelatorio());
    }

    public function test_gestor_do_macroprocesso_que_virou_executor_deixa_de_ver(): void
    {
        $this->alterarPorFora($this->gestorMacro, ['user_role' => UserRoleEnum::EXECUTOR->value]);

        $this->assertFalse(app(MoneyFieldVisibility::class)->canView($this->gestorMacro->fresh(), $this->processo));
    }

    public function test_gestor_do_cadastro_que_virou_executor_ou_inativo_deixa_de_ver(): void
    {
        [$cadastro] = $this->cadastroComValor();
        $regra = app(MoneyFieldVisibility::class);

        $this->assertTrue($regra->canView($this->gestorOutro, $cadastro));

        $this->alterarPorFora($this->gestorOutro, ['user_role' => UserRoleEnum::EXECUTOR->value]);
        $this->assertFalse($regra->canView($this->gestorOutro->fresh(), $cadastro));

        $this->alterarPorFora($this->gestorProcesso, ['is_active' => false]);
        $this->assertFalse($regra->canView($this->gestorProcesso->fresh(), $cadastro));
    }

    // ------------------------------------------------------------------
    // Busca pelos campos adicionais de cliente e projeto
    // ------------------------------------------------------------------

    private function clienteEProjetoComValor(): void
    {
        foreach ([Client::class, Project::class] as $classe) {
            $tipo = CustomRecordType::where('model_class', $classe)->where('is_system', true)->firstOrFail();
            $tipo->managers()->sync([$this->gestorProcesso->id]);

            $valor = CustomField::factory()->create([
                'name' => 'Contrato '.class_basename($classe), 'key' => 'contrato', 'field_type' => FieldTypeEnum::CURRENCY,
            ]);
            $texto = CustomField::factory()->create([
                'name' => 'Obs '.class_basename($classe), 'key' => 'obs', 'field_type' => FieldTypeEnum::TEXT,
            ]);
            $tipo->fields()->attach($valor->id, ['field_order' => 90]);
            $tipo->fields()->attach($texto->id, ['field_order' => 91]);
        }

        $cliente = Client::create(['name' => 'Cliente Busca', 'is_active' => true]);
        $cliente->forceFill(['custom_data' => ['contrato' => '987654.00', 'obs' => 'observacao-cliente']])->save();

        $projeto = Project::create(['name' => 'Projeto Busca', 'client_id' => $cliente->id, 'is_active' => true]);
        $projeto->forceFill(['custom_data' => ['contrato' => '456789.00', 'obs' => 'observacao-projeto']])->save();

        $this->demanda->update(['client_id' => $cliente->id, 'project_id' => $projeto->id]);
    }

    public function test_busca_nao_acha_demanda_pelo_valor_de_moeda_do_cliente_e_do_projeto_sem_permissao(): void
    {
        $this->clienteEProjetoComValor();

        foreach ([$this->executor, $this->gestorOutro] as $usuario) {
            $this->actingAs($usuario);

            $this->assertSame(0, Demand::query()->globalSearch('987654')->count(), $usuario->name);
            $this->assertSame(0, Demand::query()->globalSearch('456789')->count(), $usuario->name);
        }
    }

    public function test_busca_continua_achando_pelo_campo_texto_do_cliente_e_do_projeto(): void
    {
        $this->clienteEProjetoComValor();
        $this->actingAs($this->executor);

        $this->assertSame(1, Demand::query()->globalSearch('observacao-cliente')->count());
        $this->assertSame(1, Demand::query()->globalSearch('observacao-projeto')->count());
    }

    public function test_busca_acha_pelo_valor_de_moeda_do_cliente_e_do_projeto_para_gestor_e_admin(): void
    {
        $this->clienteEProjetoComValor();

        foreach ([$this->gestorProcesso, $this->admin] as $usuario) {
            $this->actingAs($usuario);

            $this->assertSame(1, Demand::query()->globalSearch('987654')->count(), $usuario->name);
            $this->assertSame(1, Demand::query()->globalSearch('456789')->count(), $usuario->name);
        }
    }

    public function test_busca_por_cliente_sem_campo_de_moeda_continua_olhando_o_json_inteiro(): void
    {
        $tipo = CustomRecordType::where('model_class', Client::class)->where('is_system', true)->firstOrFail();
        $this->assertFalse($tipo->fields()->where('field_type', FieldTypeEnum::CURRENCY->value)->exists());

        $cliente = Client::create(['name' => 'Cliente Sem Moeda', 'is_active' => true]);
        $cliente->forceFill(['custom_data' => ['qualquer' => 'conteudo-json']])->save();
        $this->demanda->update(['client_id' => $cliente->id]);

        $this->actingAs($this->executor);
        $this->assertSame(1, Demand::query()->globalSearch('conteudo-json')->count());
    }

    // ------------------------------------------------------------------
    // Regra do PO: restrição só em consultas e relatórios; formulários livres
    // ------------------------------------------------------------------

    /**
     * @return array<int, User>
     */
    private function semPermissaoNoProcesso(): array
    {
        return [$this->executor, $this->gestorOutro];
    }

    public function test_executor_ve_e_edita_o_valor_no_formulario_da_demanda_sem_apagar_o_gravado(): void
    {
        $this->actingAs($this->executor);

        $tela = Livewire::test(ViewDemand::class, ['record' => $this->demanda->id]);
        $this->assertSame('1.500,50', $tela->get('data.field_data.valor_estimado'));

        $edicao = Livewire::test(EditDemand::class, ['record' => $this->demanda->id]);
        $this->assertSame('1.500,50', $edicao->get('data.field_data.valor_estimado'));
        $this->assertStringContainsString('Valor estimado', $edicao->html());

        // Salvar outro campo não apaga nem altera o valor
        $edicao
            ->set('data.field_data.observacao', 'texto alterado')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('1500.50', $this->valorGravado());

        // E o executor consegue alterar o valor
        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.valor_estimado', '3.000,00')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('3000.00', $this->valorGravado());
    }

    public function test_tramitacao_cobra_o_valor_obrigatorio_do_executor_como_qualquer_campo(): void
    {
        FieldPermission::where('custom_field_id', $this->campoValor->id)
            ->update(['permission' => FieldPermissionEnum::REQUIRED]);
        DemandFieldValue::where('custom_field_id', $this->campoValor->id)->delete();
        $transicao = StatusTransition::where('entity_id', $this->processo->id)->firstOrFail();

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessage('Valor estimado');

        app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $this->executor);
    }

    public function test_criacao_pela_url_com_processo_usa_a_situacao_inicial_e_salva_o_valor_do_executor(): void
    {
        $componente = Livewire::actingAs($this->executor)
            ->withQueryParams(['entity_id' => $this->processo->id])
            ->test(CreateDemand::class);

        $this->assertStringContainsString('Valor estimado', $componente->html());

        $componente
            ->fillForm([
                'client_id' => Client::create(['name' => 'Cliente Criação', 'is_active' => true])->id,
                'project_id' => 'none',
                'supplier_id' => 'none',
                'title' => 'Criada pela URL',
                'field_data.valor_estimado' => '2.500,00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $criada = Demand::where('title', 'Criada pela URL')->firstOrFail();
        $this->assertSame($this->situacao->id, $criada->process_status_id);
        $this->assertSame('2500.00', DemandFieldValue::where('demand_id', $criada->id)
            ->where('custom_field_id', $this->campoValor->id)->value('value'));
    }

    public function test_quem_nao_pode_ver_o_valor_nao_o_encontra_nas_consultas_da_demanda(): void
    {
        $demanda = Demand::with(['entity.macroprocess', 'fieldValues.customField'])->find($this->demanda->id);
        $regra = app(MoneyFieldVisibility::class);

        foreach ($this->semPermissaoNoProcesso() as $usuario) {
            $this->actingAs($usuario);

            $this->assertSame(0, Demand::query()->globalSearch('1500')->count(), $usuario->name);
            $this->assertNotContains('field_'.$this->campoValor->id, $this->colunasDeCamposDoRelatorio(), $usuario->name);

            $pdf = view('pdf.demand-report', [
                'demand' => $demanda,
                'transitionsHistory' => collect(),
                'canViewMoney' => $regra->canView($usuario, $demanda->entity),
            ])->render();
            $this->assertStringNotContainsString('1.500,50', $pdf, $usuario->name);
            $this->assertStringNotContainsString('Valor estimado', $pdf, $usuario->name);
        }

        foreach ([$this->gestorProcesso, $this->gestorMacro, $this->admin] as $usuario) {
            $this->actingAs($usuario);

            $this->assertSame(1, Demand::query()->globalSearch('1500')->count(), $usuario->name);
            $this->assertContains('field_'.$this->campoValor->id, $this->colunasDeCamposDoRelatorio(), $usuario->name);

            $pdf = view('pdf.demand-report', [
                'demand' => $demanda,
                'transitionsHistory' => collect(),
                'canViewMoney' => $regra->canView($usuario, $demanda->entity),
            ])->render();
            $this->assertStringContainsString('R$ 1.500,50', $pdf, $usuario->name);
        }
    }

    public function test_executor_ve_e_edita_o_valor_no_registro_do_cadastro_mas_nao_na_listagem(): void
    {
        [$cadastro, $registro] = $this->cadastroComValor();
        // Só o criador (ou admin) edita o registro
        $registro->forceFill(['created_by' => $this->executor->id])->save();
        $colunas = fn () => array_keys(
            Livewire::test(ListCustomRecords::class, ['type_id' => $cadastro->id])->instance()->getTable()->getColumns()
        );

        // Formulário: o executor vê o valor e o grava
        $this->actingAs($this->executor);
        $tela = Livewire::test(ViewCustomRecord::class, ['record' => $registro->id, 'type_id' => $cadastro->id]);
        $this->assertSame('800,00', $tela->get('data.data_json.preco_unit'));

        $edicao = Livewire::test(EditCustomRecord::class, ['record' => $registro->id, 'type_id' => $cadastro->id]);
        $this->assertStringContainsString('Preço', $edicao->html());
        $edicao
            ->set('data.data_json.modelo', 'Impressora Y')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['modelo' => 'Impressora Y', 'preco_unit' => '800.00'], $registro->fresh()->data_json);

        Livewire::test(EditCustomRecord::class, ['record' => $registro->id, 'type_id' => $cadastro->id])
            ->set('data.data_json.preco_unit', '950,00')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('950.00', $registro->fresh()->data_json['preco_unit']);

        // Listagem: a coluna de moeda só aparece para admin e gestores do cadastro
        foreach ([$this->executor, $this->gestorMacro] as $usuario) {
            $this->actingAs($usuario);
            $this->assertContains('data_json.modelo', $colunas(), $usuario->name);
            $this->assertNotContains('data_json.preco_unit', $colunas(), $usuario->name);
        }

        foreach ([$this->gestorProcesso, $this->gestorOutro, $this->admin] as $usuario) {
            $this->actingAs($usuario);
            $this->assertContains('data_json.preco_unit', $colunas(), $usuario->name);
        }
    }

    public function test_formulario_do_cadastro_base_mostra_o_valor_e_o_model_grava_para_quem_nao_e_gestor(): void
    {
        $this->cadastroBaseComValor();
        $nomes = fn () => collect(Client::buildCustomFieldComponents()->getChildComponents())
            ->map(fn ($componente) => $componente->getName())
            ->all();

        foreach ([$this->executor, $this->gestorOutro, $this->gestorProcesso, $this->admin] as $usuario) {
            $this->actingAs($usuario);
            $this->assertContains('custom_data.contrato_anual', $nomes(), $usuario->name);
        }

        $cliente = Client::create(['name' => 'Cliente Base', 'is_active' => true]);
        $cliente->forceFill(['custom_data' => ['contrato_anual' => '120000']])->save();

        $this->actingAs($this->executor);
        $cliente->custom_data = ['contrato_anual' => '130000'];
        $cliente->save();

        $this->assertSame('130000', $cliente->fresh()->custom_data['contrato_anual']);
    }

    // ------------------------------------------------------------------
    // Campo de moeda com payload adulterado
    // ------------------------------------------------------------------

    public function test_normalize_de_array_devolve_null(): void
    {
        $this->assertNull(MoneyInput::normalize([]));
        $this->assertNull(MoneyInput::normalize(['1']));
        $this->assertNull(MoneyInput::normalize(new \stdClass));
        $this->assertNull(MoneyInput::format([]));
        $this->assertSame('—', MoneyInput::display([]));
    }

    public function test_payload_com_array_no_campo_de_valor_vira_erro_de_validacao(): void
    {
        $this->actingAs($this->gestorProcesso);

        foreach ([['1'], ['a' => ['b']]] as $adulterado) {
            Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
                ->set('data.field_data.valor_estimado', $adulterado)
                ->call('save')
                ->assertHasFormErrors(['field_data.valor_estimado']);
        }

        $this->assertSame('1500.50', $this->valorGravado());

        // Array vazio não chega à regra (valor "vazio"): não pode virar 500 nem alterar o gravado
        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.valor_estimado', [])
            ->call('save')
            ->assertOk();
    }

    public function test_relatorio_e_pdf_formatam_o_valor_com_o_formatador_unico(): void
    {
        $demanda = Demand::with(['entity.macroprocess', 'fieldValues.customField'])->find($this->demanda->id);

        $html = view('pdf.demand-report', ['demand' => $demanda, 'transitionsHistory' => collect(), 'canViewMoney' => true])->render();
        $this->assertStringContainsString(MoneyInput::display('1500.50'), $html);

        $this->actingAs($this->gestorProcesso);
        $componente = Livewire::test(DemandReports::class)
            ->set('tableFilters.entity_id.value', $this->processo->id)
            ->call('$refresh');

        $coluna = $componente->instance()->getTable()->getColumns()['field_'.$this->campoValor->id];
        $registro = Demand::with('fieldValues')->find($this->demanda->id);
        $this->assertSame('R$ 1.500,50', $coluna->record($registro)->getState());
    }

    public function test_comando_lista_valores_de_moeda_no_formato_antigo_sem_alterar_dados(): void
    {
        DemandFieldValue::where('custom_field_id', $this->campoValor->id)->update(['value' => '1.500']);
        [, $registro] = $this->cadastroComValor();
        $registro->forceFill(['data_json' => ['modelo' => 'X', 'preco_unit' => '12.345']])->save();

        $this->artisan('app:audit-currency-values')
            ->expectsOutputToContain('demand_field_values')
            ->expectsOutputToContain('custom_records.data_json')
            ->assertSuccessful();

        $this->assertSame('1.500', $this->valorGravado());
    }

    public function test_comando_nao_lista_valores_no_formato_atual(): void
    {
        $this->artisan('app:audit-currency-values')
            ->expectsOutputToContain('Nenhum valor de moeda')
            ->assertSuccessful();
    }
}
