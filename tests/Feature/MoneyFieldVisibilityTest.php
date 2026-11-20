<?php

namespace Tests\Feature;

use App\Concerns\HasCustomFields;
use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Pages\DemandReports;
use App\Filament\Resources\CustomRecordResource\Pages\ViewCustomRecord;
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
use App\Models\StatusTransition;
use App\Models\User;
use App\Services\FieldPermissionResolver;
use App\Services\MoneyFieldVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $papel = ProcessRole::factory()->create();
        foreach ([$this->gestorProcesso, $this->gestorMacro, $this->gestorOutro] as $membro) {
            ProcessMember::create([
                'entity_id' => $this->processo->id,
                'user_id' => $membro->id,
                'process_role_id' => $papel->id,
            ]);
        }

        foreach ([$this->campoValor, $this->campoTexto] as $campo) {
            FieldPermission::create([
                'entity_id' => $this->processo->id,
                'custom_field_id' => $campo->id,
                'process_status_id' => $this->situacao->id,
                'process_role_id' => null,
                'permission' => FieldPermissionEnum::OPTIONAL,
            ]);
        }

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

            $this->assertSame('1500.50', $componente->get('data.field_data.valor_estimado'));
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
            $this->assertSame('800', $componente->get('data.data_json.preco_unit'), $usuario->name);
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
}
