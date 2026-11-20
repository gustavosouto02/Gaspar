<?php

namespace Tests\Feature;

use App\Concerns\HasCustomFields;
use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\DemandTransitionException;
use App\Filament\Pages\DemandReports;
use App\Filament\Resources\CustomEntityResource\Pages\ManageFieldPermissions;
use App\Filament\Resources\CustomRecordResource\Pages\ViewCustomRecord;
use App\Filament\Resources\DemandResource;
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
use App\Models\TransitionRule;
use App\Models\User;
use App\Services\DemandTransitionService;
use App\Services\FieldPermissionResolver;
use App\Services\MoneyFieldVisibility;
use App\Support\MoneyInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T2 — campos de valor (CURRENCY) só aparecem para ADMIN e para o gestor do
 * dono do campo: o Processo (e o gestor do seu Macroprocesso) ou o Cadastro.
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

    private ProcessRole $papelGestor;

    private ProcessRole $papelSemAcesso;

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

        // Papel com acesso explícito ao valor na matriz (os gestores do processo e do macro);
        // o gestor de outro processo tem um papel sem linha para o campo de valor.
        $this->papelGestor = ProcessRole::factory()->create();
        $this->papelSemAcesso = ProcessRole::factory()->create();
        foreach ([$this->gestorProcesso, $this->gestorMacro] as $membro) {
            $this->membro($membro, $this->papelGestor);
        }
        $this->membro($this->gestorOutro, $this->papelSemAcesso);

        // Valor: só o papel dos gestores tem linha (uma linha genérica liberaria todo mundo)
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $this->papelGestor);
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
    // Resolver (cobre formulário, salvar e obrigatoriedade na tramitação)
    // ------------------------------------------------------------------

    public function test_resolver_oculta_campo_de_valor_so_de_quem_nao_pode_ver(): void
    {
        $resolver = new FieldPermissionResolver;

        $executor = $resolver->resolve($this->processo, $this->situacao, $this->executor);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $executor[$this->campoValor->id]);
        $this->assertSame(FieldPermissionEnum::OPTIONAL, $executor[$this->campoTexto->id]);

        foreach ([$this->admin, $this->gestorProcesso, $this->gestorMacro] as $usuario) {
            $permissoes = $resolver->resolve($this->processo, $this->situacao, $usuario);
            $this->assertSame(FieldPermissionEnum::OPTIONAL, $permissoes[$this->campoValor->id]);
        }

        $outro = $resolver->resolve($this->processo, $this->situacao, $this->gestorOutro);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $outro[$this->campoValor->id]);
    }

    public function test_campo_de_valor_obrigatorio_fica_oculto_e_nao_e_cobrado_de_quem_nao_ve(): void
    {
        FieldPermission::where('custom_field_id', $this->campoValor->id)
            ->update(['permission' => FieldPermissionEnum::REQUIRED]);

        $resolver = new FieldPermissionResolver;

        $this->assertSame(
            FieldPermissionEnum::HIDDEN,
            $resolver->resolve($this->processo, $this->situacao, $this->executor)[$this->campoValor->id]
        );
        $this->assertSame(
            FieldPermissionEnum::REQUIRED,
            $resolver->resolve($this->processo, $this->situacao, $this->gestorProcesso)[$this->campoValor->id]
        );
    }

    // ------------------------------------------------------------------
    // Telas da demanda
    // ------------------------------------------------------------------

    public function test_visualizacao_da_demanda_nao_envia_o_valor_para_quem_nao_ve(): void
    {
        $componente = Livewire::actingAs($this->executor)
            ->test(ViewDemand::class, ['record' => $this->demanda->id]);

        $this->assertNull($componente->get('data.field_data.valor_estimado'));
        $this->assertSame('texto original', $componente->get('data.field_data.observacao'));
        $this->assertStringNotContainsString('1500.50', json_encode($componente->get('data')));
    }

    public function test_visualizacao_da_demanda_mostra_o_valor_para_gestor_e_admin(): void
    {
        foreach ([$this->gestorProcesso, $this->gestorMacro, $this->admin] as $usuario) {
            $componente = Livewire::actingAs($usuario)
                ->test(ViewDemand::class, ['record' => $this->demanda->id]);

            $this->assertSame('1.500,50', $componente->get('data.field_data.valor_estimado'));
        }
    }

    public function test_edicao_da_demanda_oculta_o_campo_de_valor_de_quem_nao_ve(): void
    {
        $componente = Livewire::actingAs($this->executor)
            ->test(EditDemand::class, ['record' => $this->demanda->id]);

        $this->assertNull($componente->get('data.field_data.valor_estimado'));
        $this->assertStringNotContainsString('1500.50', json_encode($componente->get('data')));
    }

    public function test_salvar_a_demanda_sem_permissao_nao_apaga_nem_altera_o_valor(): void
    {
        $this->actingAs($this->executor);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.observacao', 'texto alterado')
            ->set('data.field_data.valor_estimado', '1')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('texto alterado', DemandFieldValue::where('demand_id', $this->demanda->id)
            ->where('custom_field_id', $this->campoTexto->id)->value('value'));
        $this->assertSame('1500.50', $this->valorGravado());
    }

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

    public function test_tela_do_registro_do_cadastro_oculta_o_valor_de_quem_nao_e_gestor(): void
    {
        [$cadastro, $registro] = $this->cadastroComValor();

        $executor = Livewire::actingAs($this->executor)
            ->test(ViewCustomRecord::class, ['record' => $registro->id, 'type_id' => $cadastro->id]);
        $this->assertNull($executor->get('data.data_json.preco_unit') ?? null);

        foreach ([$this->gestorProcesso, $this->gestorOutro, $this->admin] as $usuario) {
            $componente = Livewire::actingAs($usuario)
                ->test(ViewCustomRecord::class, ['record' => $registro->id, 'type_id' => $cadastro->id]);
            $this->assertSame('800,00', $componente->get('data.data_json.preco_unit'), $usuario->name);
        }
    }

    public function test_gravar_registro_do_cadastro_sem_permissao_preserva_o_valor(): void
    {
        [, $registro] = $this->cadastroComValor();

        $this->actingAs($this->executor);
        $registro->data_json = ['modelo' => 'Impressora Y'];
        $registro->save();
        $this->assertSame(['modelo' => 'Impressora Y', 'preco_unit' => '800'], $registro->fresh()->data_json);

        $registro->data_json = ['modelo' => 'Impressora Z', 'preco_unit' => '1'];
        $registro->save();
        $this->assertSame(['modelo' => 'Impressora Z', 'preco_unit' => '800'], $registro->fresh()->data_json);
    }

    public function test_gestor_do_cadastro_altera_o_valor_do_registro(): void
    {
        [, $registro] = $this->cadastroComValor();

        $this->actingAs($this->gestorOutro);
        $registro->data_json = ['modelo' => 'Impressora X', 'preco_unit' => '950'];
        $registro->save();

        $this->assertSame('950', $registro->fresh()->data_json['preco_unit']);
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

    public function test_formulario_do_cadastro_base_omite_o_campo_de_valor_de_quem_nao_e_gestor(): void
    {
        $this->cadastroBaseComValor();
        $nomes = fn () => collect(Client::buildCustomFieldComponents()->getChildComponents())
            ->map(fn ($componente) => $componente->getName())
            ->all();

        $this->actingAs($this->executor);
        $this->assertNotContains('custom_data.contrato_anual', $nomes());

        $this->actingAs($this->gestorOutro);
        $this->assertNotContains('custom_data.contrato_anual', $nomes());

        $this->actingAs($this->gestorProcesso);
        $this->assertContains('custom_data.contrato_anual', $nomes());

        $this->actingAs($this->admin);
        $this->assertContains('custom_data.contrato_anual', $nomes());
    }

    public function test_gravar_cadastro_base_sem_permissao_preserva_o_valor(): void
    {
        $this->assertContains(HasCustomFields::class, class_uses_recursive(Client::class));
        $this->cadastroBaseComValor();

        $cliente = Client::create(['name' => 'Cliente Base', 'is_active' => true]);
        $cliente->forceFill(['custom_data' => ['contrato_anual' => '120000']])->save();

        $this->actingAs($this->executor);
        $cliente->custom_data = ['outro' => 'x'];
        $cliente->save();
        $this->assertSame('120000', $cliente->fresh()->custom_data['contrato_anual']);

        $cliente->custom_data = ['contrato_anual' => '1'];
        $cliente->save();
        $this->assertSame('120000', $cliente->fresh()->custom_data['contrato_anual']);
    }

    public function test_gravar_sem_usuario_autenticado_nao_altera_nada_seeders_e_console(): void
    {
        [, $registro] = $this->cadastroComValor();

        $registro->data_json = ['modelo' => 'Via seeder', 'preco_unit' => '10'];
        $registro->save();

        $this->assertSame('10', $registro->fresh()->data_json['preco_unit']);
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
        $this->assertSame(
            FieldPermissionEnum::HIDDEN,
            (new FieldPermissionResolver)->resolve($this->processo, $this->situacao, $this->gestorOutro->fresh())[$this->campoValor->id]
        );
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
    // Liberação por linha explícita da matriz de permissões
    // ------------------------------------------------------------------

    /**
     * Executor membro do processo com um papel próprio, sem nenhuma linha para o valor.
     */
    private function executorMembro(): array
    {
        $papel = ProcessRole::factory()->create();
        $this->membro($this->executor, $papel);

        return [$this->executor, $papel];
    }

    private function permissaoDoValorPara(User $usuario): FieldPermissionEnum
    {
        return (new FieldPermissionResolver)
            ->resolve($this->processo, $this->situacao, $usuario)[$this->campoValor->id];
    }

    public function test_sem_linha_na_matriz_o_fallback_somente_leitura_nao_libera(): void
    {
        [$executor] = $this->executorMembro();

        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
        $this->assertFalse(app(MoneyFieldVisibility::class)->isReleasedByMatrix($executor, $this->campoValor, $this->processo, $this->situacao));
    }

    public function test_linha_oculto_nao_libera(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::HIDDEN, $papel);

        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
    }

    public function test_executor_com_linha_explicita_opcional_ou_obrigatoria_recebe_a_permissao_da_matriz(): void
    {
        [$executor, $papel] = $this->executorMembro();

        foreach ([FieldPermissionEnum::OPTIONAL, FieldPermissionEnum::REQUIRED] as $permissao) {
            $this->permissao($this->campoValor, $permissao, $papel);

            $this->assertSame($permissao, $this->permissaoDoValorPara($executor));
        }
    }

    public function test_linha_somente_leitura_nao_libera_nem_a_do_papel_nem_a_generica(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $regra = app(MoneyFieldVisibility::class);

        $this->permissao($this->campoValor, FieldPermissionEnum::READONLY, $papel);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
        $this->assertFalse($regra->isReleasedByMatrix($executor, $this->campoValor, $this->processo, $this->situacao));
        $this->assertTrue($regra->isHiddenInDemand($executor, $this->campoValor, $this->processo, $this->situacao));

        FieldPermission::where('custom_field_id', $this->campoValor->id)->where('process_role_id', $papel->id)->delete();
        $this->permissao($this->campoValor, FieldPermissionEnum::READONLY);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
    }

    public function test_generica_opcional_com_papel_somente_leitura_nao_libera_porque_o_papel_vence(): void
    {
        [$executor, $papel] = $this->executorMembro();

        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL);
        $this->assertSame(FieldPermissionEnum::OPTIONAL, $this->permissaoDoValorPara($executor));

        $this->permissao($this->campoValor, FieldPermissionEnum::READONLY, $papel);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
        $this->assertFalse(app(MoneyFieldVisibility::class)->isReleasedByMatrix($executor, $this->campoValor, $this->processo, $this->situacao));
    }

    public function test_entre_varios_papeis_vale_o_mais_permissivo_para_liberar(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $outroPapel = ProcessRole::factory()->create();
        $this->membro($executor, $outroPapel);

        $this->permissao($this->campoValor, FieldPermissionEnum::READONLY, $papel);
        $this->permissao($this->campoValor, FieldPermissionEnum::HIDDEN, $outroPapel);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));

        $this->permissao($this->campoValor, FieldPermissionEnum::REQUIRED, $outroPapel);
        $this->assertSame(FieldPermissionEnum::REQUIRED, $this->permissaoDoValorPara($executor));
    }

    public function test_linha_generica_explicita_tambem_libera_e_a_do_papel_vence_a_generica(): void
    {
        [$executor, $papel] = $this->executorMembro();

        $this->permissao($this->campoValor, FieldPermissionEnum::REQUIRED);
        $this->assertSame(FieldPermissionEnum::REQUIRED, $this->permissaoDoValorPara($executor));

        $this->permissao($this->campoValor, FieldPermissionEnum::HIDDEN, $papel);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
    }

    public function test_liberacao_vale_so_para_a_situacao_da_linha(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $papel);

        $outraSituacao = $this->processo->processStatuses()->where('system_key', 'closed')->firstOrFail();
        $permissoes = (new FieldPermissionResolver)->resolve($this->processo, $outraSituacao, $executor);

        $this->assertSame(FieldPermissionEnum::HIDDEN, $permissoes[$this->campoValor->id]);
    }

    public function test_executor_liberado_ve_o_valor_na_demanda_e_salva_quando_opcional(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $papel);

        $componente = Livewire::actingAs($executor)
            ->test(EditDemand::class, ['record' => $this->demanda->id]);
        $this->assertSame('1.500,50', $componente->get('data.field_data.valor_estimado'));

        $componente
            ->set('data.field_data.valor_estimado', '3.000,00')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('3000.00', $this->valorGravado());
    }

    public function test_executor_com_linha_somente_leitura_nao_ve_nem_altera_o_valor(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::READONLY, $papel);

        $componente = Livewire::actingAs($executor)
            ->test(EditDemand::class, ['record' => $this->demanda->id]);
        $this->assertNull($componente->get('data.field_data.valor_estimado'));
        $this->assertStringNotContainsString('Valor estimado', $componente->html());

        $componente
            ->set('data.field_data.valor_estimado', '1')
            ->call('save');

        $this->assertSame('1500.50', $this->valorGravado());
    }

    public function test_executor_liberado_com_campo_obrigatorio_e_cobrado_na_tramitacao(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::REQUIRED, $papel);
        DemandFieldValue::where('custom_field_id', $this->campoValor->id)->delete();

        $transicao = StatusTransition::where('entity_id', $this->processo->id)->firstOrFail();

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessage('Valor estimado');

        app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $executor);
    }

    public function test_executor_sem_liberacao_nao_e_cobrado_do_valor_na_tramitacao(): void
    {
        Notification::fake();
        [$executor] = $this->executorMembro();
        DemandFieldValue::where('custom_field_id', $this->campoValor->id)->delete();

        $transicao = StatusTransition::where('entity_id', $this->processo->id)->firstOrFail();

        $destino = app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $executor);

        $this->assertSame('closed', $destino->system_key);
    }

    public function test_relatorio_pdf_e_busca_continuam_restritos_mesmo_com_liberacao_na_matriz(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $papel);

        $this->actingAs($executor);

        $this->assertFalse(app(MoneyFieldVisibility::class)->canView($executor, $this->processo));
        $this->assertSame(0, Demand::query()->globalSearch('1500')->count());
        $this->assertNotContains('field_'.$this->campoValor->id, $this->colunasDeCamposDoRelatorio());
    }

    // ------------------------------------------------------------------
    // Gateway por valor com campo que o ator não enxerga
    // ------------------------------------------------------------------

    private function transicaoCondicionalPorValor(): StatusTransition
    {
        $condicional = ProcessStatus::findBySystemKey('conditional');
        $fechada = $this->processo->processStatuses()->where('system_key', 'closed')->firstOrFail();

        $transicao = StatusTransition::create([
            'entity_id' => $this->processo->id,
            'from_status_id' => $this->situacao->id,
            'to_status_id' => $condicional->id,
            'default_to_status_id' => $fechada->id,
            'label' => DemandButtonEnum::cases()[0]->value,
            'allowed_role_ids' => [],
            'display_order' => 1,
        ]);

        TransitionRule::create([
            'status_transition_id' => $transicao->id,
            'rule_order' => 0,
            'conditions' => [['field_id' => $this->campoValor->id, 'operator' => 'gt', 'value' => '1000']],
            'to_status_id' => $fechada->id,
        ]);

        DemandFieldValue::where('custom_field_id', $this->campoValor->id)->delete();

        return $transicao;
    }

    public function test_gateway_por_valor_pede_para_aguardar_o_gestor_quando_o_ator_nao_enxerga_o_campo(): void
    {
        [$executor] = $this->executorMembro();
        $transicao = $this->transicaoCondicionalPorValor();

        try {
            app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $executor);
            $this->fail('A transição deveria aguardar o gestor.');
        } catch (DemandTransitionException $e) {
            $this->assertStringContainsString('Aguardando o gestor do processo ou um administrador informar', $e->getMessage());
            $this->assertStringContainsString('Valor estimado', $e->getMessage());
            $this->assertStringNotContainsString('precisa estar preenchido', $e->getMessage());
            $this->assertStringNotContainsString('sem gestor', $e->getMessage());
        }
    }

    public function test_gateway_por_valor_avisa_que_so_o_admin_informa_quando_processo_e_macro_estao_sem_gestor(): void
    {
        [$executor] = $this->executorMembro();
        $transicao = $this->transicaoCondicionalPorValor();

        // Só o gestor do macroprocesso: ainda há quem informe
        $this->processo->update(['manager_id' => null]);
        try {
            app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $executor);
            $this->fail('A transição deveria aguardar o gestor.');
        } catch (DemandTransitionException $e) {
            $this->assertStringContainsString('Aguardando o gestor do processo ou um administrador informar', $e->getMessage());
        }

        // Processo e macroprocesso sem gestor: só um administrador pode informar
        $this->processo->macroprocess->update(['manager_id' => null]);
        try {
            app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $executor);
            $this->fail('A transição deveria avisar que não há gestor.');
        } catch (DemandTransitionException $e) {
            $this->assertStringContainsString('O processo está sem gestor', $e->getMessage());
            $this->assertStringContainsString('só um administrador pode informar o campo "Valor estimado"', $e->getMessage());
            $this->assertStringNotContainsString('Aguardando', $e->getMessage());
        }
    }

    public function test_gateway_por_valor_mantem_a_mensagem_original_para_quem_enxerga_o_campo(): void
    {
        $transicao = $this->transicaoCondicionalPorValor();

        try {
            app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $this->gestorProcesso);
            $this->fail('A transição deveria exigir o valor.');
        } catch (DemandTransitionException $e) {
            $this->assertStringContainsString('precisa estar preenchido', $e->getMessage());
        }
    }

    public function test_gateway_por_valor_avalia_normalmente_quando_o_ator_foi_liberado_na_matriz(): void
    {
        Notification::fake();
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $papel);
        $transicao = $this->transicaoCondicionalPorValor();

        DemandFieldValue::create([
            'demand_id' => $this->demanda->id,
            'custom_field_id' => $this->campoValor->id,
            'value' => '5000.00',
        ]);

        $destino = app(DemandTransitionService::class)->execute($this->demanda->fresh(), $transicao, $executor);

        $this->assertSame('closed', $destino->system_key);
    }

    // ------------------------------------------------------------------
    // Criação com ?entity_id= (sem situação resolvida)
    // ------------------------------------------------------------------

    public function test_criacao_com_processo_pre_selecionado_nao_mostra_o_campo_de_valor_ao_executor(): void
    {
        $nomes = fn (User $usuario) => collect(DemandResource::buildDynamicFieldSchema($this->processo->id, null, $usuario))
            ->map(fn ($componente) => $componente->getName())
            ->all();

        $this->assertNotContains('field_data.valor_estimado', $nomes($this->executor));
        $this->assertContains('field_data.observacao', $nomes($this->executor));
        $this->assertContains('field_data.valor_estimado', $nomes($this->gestorProcesso));
        $this->assertContains('field_data.valor_estimado', $nomes($this->admin));

        $this->actingAs($this->executor);
        $this->assertNotContains('field_data.valor_estimado', $nomes($this->executor));
    }

    public function test_criacao_pela_url_com_processo_nao_renderiza_o_campo_de_valor_ao_executor(): void
    {
        $this->actingAs($this->executor);

        $componente = Livewire::withQueryParams(['entity_id' => $this->processo->id])
            ->test(CreateDemand::class);

        $html = $componente->html();
        $this->assertStringNotContainsString('Valor estimado', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosDeCriacao(string $titulo): array
    {
        return [
            'client_id' => Client::create(['name' => 'Cliente Criação', 'is_active' => true])->id,
            'project_id' => 'none',
            'supplier_id' => 'none',
            'title' => $titulo,
            'field_data.valor_estimado' => '2.500,00',
        ];
    }

    private function valorDaDemandaCriada(string $titulo): ?string
    {
        $criada = Demand::where('title', $titulo)->firstOrFail();

        return DemandFieldValue::where('demand_id', $criada->id)
            ->where('custom_field_id', $this->campoValor->id)
            ->value('value');
    }

    public function test_criacao_pela_url_respeita_linha_opcional_na_situacao_inicial_do_executor(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $papel);

        $componente = Livewire::actingAs($executor)
            ->withQueryParams(['entity_id' => $this->processo->id])
            ->test(CreateDemand::class);
        $this->assertStringContainsString('Valor estimado', $componente->html());

        $componente
            ->fillForm($this->dadosDeCriacao('Criada com liberação'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('2500.00', $this->valorDaDemandaCriada('Criada com liberação'));
    }

    public function test_criacao_pela_url_sem_linha_na_situacao_inicial_nao_mostra_nem_salva_o_valor(): void
    {
        [$executor] = $this->executorMembro();

        $componente = Livewire::actingAs($executor)
            ->withQueryParams(['entity_id' => $this->processo->id])
            ->test(CreateDemand::class);
        $this->assertStringNotContainsString('Valor estimado', $componente->html());

        $componente
            ->fillForm($this->dadosDeCriacao('Criada sem liberação'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull($this->valorDaDemandaCriada('Criada sem liberação'));
    }

    public function test_criacao_sem_situacao_resolve_a_inicial_e_sem_inicial_resolvivel_mantem_restrito(): void
    {
        [$executor, $papel] = $this->executorMembro();
        $this->permissao($this->campoValor, FieldPermissionEnum::OPTIONAL, $papel);
        $nomes = fn (bool $criando) => collect(DemandResource::buildDynamicFieldSchema($this->processo->id, null, $executor, creating: $criando))
            ->map(fn ($componente) => $componente->getName())
            ->all();

        // Situação nula na criação: vale a situação inicial e a linha OPTIONAL libera
        $this->assertContains('field_data.valor_estimado', $nomes(true));
        // Fora da criação, sem situação, segue restrito
        $this->assertNotContains('field_data.valor_estimado', $nomes(false));

        // Sem situação inicial resolvível, segue restrito mesmo na criação
        $this->processo->processStatuses()->detach();
        $this->assertNotContains('field_data.valor_estimado', $nomes(true));
        $this->assertContains('field_data.observacao', $nomes(true));
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

    // ------------------------------------------------------------------
    // Matriz de permissões e valores antigos
    // ------------------------------------------------------------------

    public function test_salvar_a_matriz_grava_somente_leitura_para_campo_de_valor_sem_liberar(): void
    {
        $this->actingAs($this->admin);
        FieldPermission::where('custom_field_id', $this->campoValor->id)->delete();

        [$executor, $papel] = $this->executorMembro();

        $componente = Livewire::test(ManageFieldPermissions::class, ['record' => $this->processo->id])
            ->set('roleId', $papel->id)
            ->call('save');

        // A grade grava todas as células, inclusive o padrão Somente leitura do campo de valor
        $this->assertDatabaseHas('field_permissions', [
            'custom_field_id' => $this->campoValor->id,
            'process_role_id' => $papel->id,
            'permission' => 'READONLY',
        ]);
        $this->assertDatabaseHas('field_permissions', ['custom_field_id' => $this->campoTexto->id, 'permission' => 'READONLY']);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));

        $componente
            ->set('matrix.'.$this->campoValor->id.'.'.$this->situacao->id, FieldPermissionEnum::OPTIONAL->value)
            ->call('save');
        $this->assertSame(FieldPermissionEnum::OPTIONAL, $this->permissaoDoValorPara($executor));

        // Voltar para Somente leitura grava a linha e deixa de liberar
        $componente
            ->set('matrix.'.$this->campoValor->id.'.'.$this->situacao->id, FieldPermissionEnum::READONLY->value)
            ->call('save');
        $this->assertDatabaseHas('field_permissions', [
            'custom_field_id' => $this->campoValor->id,
            'process_role_id' => $papel->id,
            'permission' => 'READONLY',
        ]);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->permissaoDoValorPara($executor));
    }

    public function test_campo_number_com_linhas_da_grade_que_vira_moeda_nao_libera_o_valor_ao_executor(): void
    {
        $campo = CustomField::factory()->create([
            'name' => 'Preço',
            'key' => 'preco',
            'field_type' => FieldTypeEnum::NUMBER,
        ]);
        $this->processo->fields()->attach($campo->id, ['field_order' => 2]);
        DemandFieldValue::create(['demand_id' => $this->demanda->id, 'custom_field_id' => $campo->id, 'value' => '777.00']);
        [$executor, $papel] = $this->executorMembro();

        // A grade (genérica e do papel) grava Somente leitura para o campo ainda NUMBER
        $this->actingAs($this->admin);
        foreach ([null, $papel->id] as $papelDaGrade) {
            Livewire::test(ManageFieldPermissions::class, ['record' => $this->processo->id])
                ->set('roleId', $papelDaGrade)
                ->call('save');
        }
        $this->assertDatabaseHas('field_permissions', ['custom_field_id' => $campo->id, 'permission' => 'READONLY', 'process_role_id' => $papel->id]);

        // Troca do tipo para moeda: as linhas READONLY gravadas não expõem o valor
        $campo->update(['field_type' => FieldTypeEnum::CURRENCY]);

        $permissoes = (new FieldPermissionResolver)->resolve($this->processo, $this->situacao, $executor);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $permissoes[$campo->id]);
        $this->assertTrue(app(MoneyFieldVisibility::class)->isHiddenInDemand($executor, $campo->fresh(), $this->processo, $this->situacao));

        $componente = Livewire::actingAs($executor)
            ->test(EditDemand::class, ['record' => $this->demanda->id]);
        $this->assertNull($componente->get('data.field_data.preco'));
        $this->assertStringNotContainsString('777', $componente->html());
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
