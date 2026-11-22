<?php

namespace Tests\Feature;

use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Pages\CadastroReports;
use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CadastroReportSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T5 — Relatório de Cadastros: escolhe o cadastro, filtra pelos campos dele e
 * exporta CSV, para cadastros criados pelo admin e para os base.
 */
class CadastroReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gestor;

    private User $executor;

    private CustomRecordType $cadastro;

    private CustomRecord $impressora;

    private CustomRecord $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->gestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);
        $this->executor = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);

        $this->cadastro = CustomRecordType::create(['name' => 'Equipamentos', 'slug' => 'equipamentos', 'is_system' => false]);
        $this->cadastro->managers()->attach($this->gestor->id);

        $campos = [
            ['Tipo', 'tipo', FieldTypeEnum::SELECT, ['Impressora', 'Notebook']],
            ['Modelo', 'modelo', FieldTypeEnum::TEXT, null],
            ['Cidade', 'cidade', FieldTypeEnum::TEXT, null],
            ['Compra', 'compra', FieldTypeEnum::DATE, null],
            ['Preço', 'preco', FieldTypeEnum::CURRENCY, null],
        ];
        foreach ($campos as $ordem => [$nome, $chave, $tipo, $opcoes]) {
            $campo = CustomField::factory()->create([
                'name' => $nome, 'key' => $chave, 'field_type' => $tipo, 'options_json' => $opcoes,
            ]);
            $this->cadastro->fields()->attach($campo->id, ['field_order' => $ordem]);
        }

        $this->impressora = $this->registro([
            'tipo' => 'Impressora', 'modelo' => 'Laser X', 'cidade' => 'Recife', 'compra' => '2026-03-10', 'preco' => '800',
        ]);
        $this->notebook = $this->registro([
            'tipo' => 'Notebook', 'modelo' => 'Pro 14', 'cidade' => 'Natal', 'compra' => '2026-09-01', 'preco' => '5000',
        ]);
    }

    private function registro(array $dados): CustomRecord
    {
        return CustomRecord::create([
            'custom_record_type_id' => $this->cadastro->id,
            'created_by' => $this->admin->id,
            'data_json' => $dados,
        ]);
    }

    private function pagina(?User $usuario = null)
    {
        $this->actingAs($usuario ?? $this->admin);

        return Livewire::test(CadastroReports::class, ['type_id' => $this->cadastro->id]);
    }

    private function campo(string $chave): CustomField
    {
        return CustomField::where('key', $chave)->firstOrFail();
    }

    private function filtro(string $chave, array $dados): array
    {
        return ['field_'.$this->campo($chave)->id => $dados];
    }

    public function test_sem_cadastro_escolhido_nao_lista_nada(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CadastroReports::class)->assertSuccessful()->assertDontSee('Laser X');
    }

    public function test_lista_os_registros_com_as_colunas_dos_campos(): void
    {
        $this->pagina()
            ->assertCanSeeTableRecords([$this->impressora, $this->notebook])
            ->assertSee('Laser X')
            ->assertSee('Recife');
    }

    public function test_filtra_por_select_e_texto(): void
    {
        $this->pagina()
            ->filterTable('field_'.$this->campo('tipo')->id, ['value' => 'Impressora'])
            ->assertCanSeeTableRecords([$this->impressora])
            ->assertCanNotSeeTableRecords([$this->notebook]);

        $this->pagina()
            ->filterTable('field_'.$this->campo('cidade')->id, ['value' => 'nat'])
            ->assertCanSeeTableRecords([$this->notebook])
            ->assertCanNotSeeTableRecords([$this->impressora]);
    }

    public function test_filtra_por_mais_de_um_campo_ao_mesmo_tempo(): void
    {
        $this->pagina()
            ->filterTable('field_'.$this->campo('tipo')->id, ['value' => 'Impressora'])
            ->filterTable('field_'.$this->campo('cidade')->id, ['value' => 'Natal'])
            ->assertCanNotSeeTableRecords([$this->impressora, $this->notebook]);
    }

    public function test_filtra_por_periodo_de_data_e_faixa_de_valor(): void
    {
        $this->pagina()
            ->filterTable('field_'.$this->campo('compra')->id, ['from' => '2026-08-01', 'until' => null])
            ->assertCanSeeTableRecords([$this->notebook])
            ->assertCanNotSeeTableRecords([$this->impressora]);

        $this->pagina()
            ->filterTable('field_'.$this->campo('preco')->id, ['min' => 100, 'max' => 1000])
            ->assertCanSeeTableRecords([$this->impressora])
            ->assertCanNotSeeTableRecords([$this->notebook]);
    }

    public function test_campo_de_valor_aparece_so_para_admin_e_gestor_do_cadastro(): void
    {
        $idPreco = 'field_'.$this->campo('preco')->id;
        $colunas = fn (User $u) => array_keys($this->pagina($u)->instance()->getTable()->getColumns());
        $filtros = fn (User $u) => array_keys($this->pagina($u)->instance()->getTable()->getFilters());

        $this->assertContains($idPreco, $colunas($this->admin));
        $this->assertContains($idPreco, $colunas($this->gestor));
        $this->assertNotContains($idPreco, $colunas($this->executor));

        // O filtro de faixa de valor também some: senão daria para descobrir valores por tentativa
        $this->assertContains($idPreco, $filtros($this->admin));
        $this->assertNotContains($idPreco, $filtros($this->executor));
    }

    public function test_csv_traz_os_registros_filtrados_e_omite_valor_de_quem_nao_pode_ver(): void
    {
        $filtros = $this->filtro('tipo', ['value' => 'Impressora']);

        $csv = $this->pagina()->set('tableFilters', $filtros)->callTableAction('export_csv');
        $csv->assertFileDownloaded('relatorio_equipamentos.csv');

        $conteudoAdmin = $this->conteudoDoCsv($this->admin, $filtros);
        $this->assertStringContainsString('Laser X', $conteudoAdmin);
        $this->assertStringNotContainsString('Pro 14', $conteudoAdmin);
        $this->assertStringContainsString('R$ 800,00', $conteudoAdmin);

        $conteudoExecutor = $this->conteudoDoCsv($this->executor, $filtros);
        $this->assertStringContainsString('Laser X', $conteudoExecutor);
        $this->assertStringNotContainsString('R$ 800,00', $conteudoExecutor);
        $this->assertStringNotContainsString('Preço', $conteudoExecutor);
    }

    public function test_csv_neutraliza_texto_que_o_excel_leria_como_formula(): void
    {
        $this->registro(['tipo' => 'Notebook', 'modelo' => '=HYPERLINK("http://x")', 'cidade' => 'Recife']);

        $conteudo = $this->conteudoDoCsv($this->admin, $this->filtro('cidade', ['value' => 'Recife']));

        $this->assertStringContainsString("'=HYPERLINK", $conteudo);
    }

    public function test_cadastro_base_lista_colunas_fixas_e_campos_extras(): void
    {
        $tipo = CustomRecordType::where('model_class', Client::class)->where('is_system', true)->firstOrFail();
        $campo = CustomField::factory()->create(['name' => 'Segmento', 'key' => 'segmento', 'field_type' => FieldTypeEnum::TEXT]);
        $tipo->fields()->attach($campo->id, ['field_order' => 0]);

        $ativo = Client::create(['name' => 'Cliente Ativo', 'is_active' => true, 'custom_data' => ['segmento' => 'Saúde']]);
        $inativo = Client::create(['name' => 'Cliente Inativo', 'is_active' => false, 'custom_data' => ['segmento' => 'Varejo']]);

        $this->actingAs($this->admin);
        $pagina = Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->assertCanSeeTableRecords([$ativo, $inativo])
            ->assertSee('Saúde');

        $pagina->filterTable('field_'.$campo->id, ['value' => 'Var'])
            ->assertCanSeeTableRecords([$inativo])
            ->assertCanNotSeeTableRecords([$ativo]);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$ativo])
            ->assertCanNotSeeTableRecords([$inativo]);
    }

    private function tipoBase(string $modelo): CustomRecordType
    {
        return CustomRecordType::where('model_class', $modelo)->where('is_system', true)->firstOrFail();
    }

    private function macro(string $nome, string $funcao, ?User $gestor = null): Macroprocess
    {
        return Macroprocess::create([
            'name' => $nome, 'acronym' => strtoupper(substr($nome, 0, 3)), 'description' => 'Descrição '.$nome,
            'is_active' => true, 'value_chain_function' => $funcao,
            'created_by' => $this->admin->id, 'manager_id' => $gestor?->id,
        ]);
    }

    public function test_macroprocesso_filtra_por_funcao_na_cadeia_de_valor_e_por_gestor(): void
    {
        $apoio = $this->macro('Compras', 'Suportar Processos', $this->gestor);
        $entrega = $this->macro('Entrega', 'Entregar Produtos e Serviços');
        $tipo = $this->tipoBase(Macroprocess::class);

        $this->actingAs($this->admin);
        $pagina = Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->assertCanSeeTableRecords([$apoio, $entrega])
            ->assertSee('Função na Cadeia de Valor')
            ->assertSee('Gestor');

        $pagina->filterTable('fixed_value_chain_function', ['values' => ['Suportar Processos']])
            ->assertCanSeeTableRecords([$apoio])
            ->assertCanNotSeeTableRecords([$entrega]);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->filterTable('fixed_manager_id', ['values' => [$this->gestor->id]])
            ->assertCanSeeTableRecords([$apoio])
            ->assertCanNotSeeTableRecords([$entrega]);
    }

    public function test_filtro_de_gestor_lista_so_quem_gerencia_algum_macroprocesso(): void
    {
        $this->macro('Compras', 'Suportar Processos', $this->gestor);
        $this->macro('Entrega', 'Entregar Produtos e Serviços');

        $opcoes = CadastroReportSource::for($this->tipoBase(Macroprocess::class))->fixedOptions('manager_id');

        // O admin e o executor existem como usuários, mas não gerenciam nada
        $this->assertSame([$this->gestor->id => $this->gestor->name], $opcoes);
    }

    public function test_opcoes_da_funcao_na_cadeia_vem_do_que_esta_gravado(): void
    {
        $this->macro('Compras', 'Suportar Processos');
        $this->macro('Entrega', 'Entregar Produtos e Serviços');

        $fonte = CadastroReportSource::for($this->tipoBase(Macroprocess::class));

        $this->assertSame(
            ['Entregar Produtos e Serviços', 'Suportar Processos'],
            array_keys($fonte->fixedOptions('value_chain_function'))
        );
    }

    public function test_projeto_filtra_por_cliente_e_periodo_e_exporta_csv_com_as_colunas(): void
    {
        $a = Client::create(['name' => 'Cliente A', 'is_active' => true]);
        $b = Client::create(['name' => 'Cliente B', 'is_active' => true]);
        $pa = Project::create(['name' => 'Projeto A', 'acronym' => 'PA', 'client_id' => $a->id, 'is_active' => true, 'start_date_forecast' => '2026-02-01', 'sponsor_name' => 'Maria']);
        $pb = Project::create(['name' => 'Projeto B', 'acronym' => 'PB', 'client_id' => $b->id, 'is_active' => true, 'start_date_forecast' => '2026-10-01']);
        $tipo = $this->tipoBase(Project::class);

        $this->actingAs($this->admin);
        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->filterTable('fixed_client_id', ['values' => [$a->id]])
            ->assertCanSeeTableRecords([$pa])
            ->assertCanNotSeeTableRecords([$pb]);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->filterTable('fixed_start_date_forecast', ['from' => '2026-09-01', 'until' => null])
            ->assertCanSeeTableRecords([$pb])
            ->assertCanNotSeeTableRecords([$pa]);

        $csv = base64_decode(
            Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
                ->callTableAction('export_csv')->effects['download']['content']
        );
        $this->assertStringContainsString('Patrocinador', $csv);
        $this->assertStringContainsString('Cliente A', $csv);
        $this->assertStringContainsString('Maria', $csv);
        $this->assertStringContainsString('01/10/2026', $csv);
        // Orçamento e gasto não fazem parte do relatório
        $this->assertStringNotContainsString('Orçamento', $csv);
    }

    public function test_usuario_filtra_por_perfil(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CadastroReports::class, ['type_id' => $this->tipoBase(User::class)->id])
            ->filterTable('fixed_user_role', ['values' => ['EXECUTOR']])
            ->assertCanSeeTableRecords([$this->executor])
            ->assertCanNotSeeTableRecords([$this->admin, $this->gestor]);
    }

    public function test_cadastro_criado_pelo_admin_filtra_por_quem_criou_e_data(): void
    {
        $outro = CustomRecord::create([
            'custom_record_type_id' => $this->cadastro->id, 'created_by' => $this->gestor->id,
            'data_json' => ['tipo' => 'Notebook'],
        ]);

        $this->pagina()
            ->filterTable('fixed_created_by', ['values' => [$this->gestor->id]])
            ->assertCanSeeTableRecords([$outro])
            ->assertCanNotSeeTableRecords([$this->impressora]);

        $this->pagina()
            ->filterTable('fixed_created_at', ['from' => now()->addDay()->toDateString(), 'until' => null])
            ->assertCanNotSeeTableRecords([$outro, $this->impressora]);
    }

    public function test_campo_combo_vira_filtro_e_mostra_o_nome_do_registro_vinculado(): void
    {
        $fornecedorA = Supplier::create(['name' => 'Fornecedor A']);
        $fornecedorB = Supplier::create(['name' => 'Fornecedor B']);
        $combo = CustomField::factory()->create([
            'name' => 'Fornecedor', 'key' => 'fornecedor', 'field_type' => FieldTypeEnum::COMBO,
            'options_json' => ['source' => 'supplier'],
        ]);
        $this->cadastro->fields()->attach($combo->id, ['field_order' => 9]);
        $this->impressora->update(['data_json' => [...$this->impressora->data_json, 'fornecedor' => $fornecedorA->id]]);
        $this->notebook->update(['data_json' => [...$this->notebook->data_json, 'fornecedor' => $fornecedorB->id]]);

        // Cadastro criado pelo admin
        $this->pagina()
            ->assertSee('Fornecedor A')
            ->filterTable('field_'.$combo->id, ['value' => $fornecedorA->id])
            ->assertCanSeeTableRecords([$this->impressora])
            ->assertCanNotSeeTableRecords([$this->notebook]);

        // Cadastro base
        $tipo = $this->tipoBase(Client::class);
        $tipo->fields()->attach($combo->id, ['field_order' => 0]);
        $a = Client::create(['name' => 'Cliente A', 'custom_data' => ['fornecedor' => $fornecedorA->id]]);
        $b = Client::create(['name' => 'Cliente B', 'custom_data' => ['fornecedor' => $fornecedorB->id]]);

        $this->actingAs($this->admin);
        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->assertSee('Fornecedor B')
            ->filterTable('field_'.$combo->id, ['value' => $fornecedorB->id])
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a]);
    }

    public function test_campo_novo_vinculado_a_um_cadastro_base_ja_vira_coluna_e_filtro(): void
    {
        $tipo = $this->tipoBase(User::class);
        $this->actingAs($this->admin);

        // Antes de vincular, o campo não existe no relatório
        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])->assertDontSee('Ramal');

        $campo = CustomField::factory()->create(['name' => 'Ramal', 'key' => 'ramal', 'field_type' => FieldTypeEnum::NUMBER]);
        $tipo->fields()->attach($campo->id, ['field_order' => 0]);
        $this->executor->update(['custom_data' => ['ramal' => '200']]);
        $this->gestor->update(['custom_data' => ['ramal' => '900']]);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->assertSee('Ramal')
            ->filterTable('field_'.$campo->id, ['min' => 500, 'max' => null])
            ->assertCanSeeTableRecords([$this->gestor])
            ->assertCanNotSeeTableRecords([$this->executor]);
    }

    public function test_todos_os_cadastros_base_abrem_no_relatorio(): void
    {
        $this->actingAs($this->admin);

        $tipos = CadastroReportSource::availableTypes();
        $this->assertGreaterThanOrEqual(5, $tipos->where('is_system', true)->count());

        foreach ($tipos as $tipo) {
            Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])->assertSuccessful();
        }
    }

    public function test_cadastro_inativo_ou_inexistente_nao_e_aceito(): void
    {
        $this->cadastro->update(['is_active' => false]);

        $this->actingAs($this->admin);
        Livewire::test(CadastroReports::class, ['type_id' => $this->cadastro->id])
            ->assertDontSee('Laser X');

        Livewire::test(CadastroReports::class, ['type_id' => 'nao-existe'])->assertSuccessful();
    }

    public function test_filtro_de_texto_trata_percentual_e_underscore_como_texto(): void
    {
        $cem = $this->registro(['modelo' => '100%', 'cidade' => 'Olinda']);
        $mil = $this->registro(['modelo' => '1000', 'cidade' => 'Olinda']);
        $sublinhado = $this->registro(['modelo' => 'a_b', 'cidade' => 'Olinda']);
        $outro = $this->registro(['modelo' => 'axb', 'cidade' => 'Olinda']);
        $exclamacao = $this->registro(['modelo' => 'oi!', 'cidade' => 'Olinda']);

        $this->pagina()
            ->filterTable('field_'.$this->campo('modelo')->id, ['value' => '100%'])
            ->assertCanSeeTableRecords([$cem])
            ->assertCanNotSeeTableRecords([$mil, $this->impressora]);

        $this->pagina()
            ->filterTable('field_'.$this->campo('modelo')->id, ['value' => 'a_b'])
            ->assertCanSeeTableRecords([$sublinhado])
            ->assertCanNotSeeTableRecords([$outro]);

        $this->pagina()
            ->filterTable('field_'.$this->campo('modelo')->id, ['value' => '!'])
            ->assertCanSeeTableRecords([$exclamacao])
            ->assertCanNotSeeTableRecords([$cem, $outro]);

        // Sem diferenciar caixa
        $this->pagina()
            ->filterTable('field_'.$this->campo('modelo')->id, ['value' => 'LASER'])
            ->assertCanSeeTableRecords([$this->impressora]);
    }

    public function test_filtro_de_texto_das_colunas_fixas_trata_percentual_como_texto(): void
    {
        $cem = Client::create(['name' => '100% Pronto', 'is_active' => true]);
        $mil = Client::create(['name' => '1000 Pronto', 'is_active' => true]);
        $sublinhado = Client::create(['name' => 'a_b', 'is_active' => true]);
        $outro = Client::create(['name' => 'axb', 'is_active' => true]);

        $this->actingAs($this->admin);
        $tipo = $this->tipoBase(Client::class);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->filterTable('fixed_name', ['value' => '100%'])
            ->assertCanSeeTableRecords([$cem])
            ->assertCanNotSeeTableRecords([$mil]);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->filterTable('fixed_name', ['value' => 'a_b'])
            ->assertCanSeeTableRecords([$sublinhado])
            ->assertCanNotSeeTableRecords([$outro]);
    }

    public function test_json_null_nao_entra_nos_filtros_de_data_e_faixa(): void
    {
        $semData = $this->registro(['modelo' => 'Sem data', 'compra' => null, 'preco' => null]);
        $zero = $this->registro(['modelo' => 'Zero', 'compra' => '2026-05-01', 'preco' => '0']);

        $this->pagina()
            ->filterTable('field_'.$this->campo('compra')->id, ['from' => '2000-01-01', 'until' => null])
            ->assertCanSeeTableRecords([$this->impressora, $zero])
            ->assertCanNotSeeTableRecords([$semData]);

        $this->pagina()
            ->filterTable('field_'.$this->campo('compra')->id, ['from' => null, 'until' => '2100-01-01'])
            ->assertCanNotSeeTableRecords([$semData]);

        // Faixa que inclui 0: o 0 gravado entra, o null não
        $this->pagina()
            ->filterTable('field_'.$this->campo('preco')->id, ['min' => -10, 'max' => 10])
            ->assertCanSeeTableRecords([$zero])
            ->assertCanNotSeeTableRecords([$semData, $this->impressora]);

        $this->pagina()
            ->filterTable('field_'.$this->campo('preco')->id, ['min' => null, 'max' => 10])
            ->assertCanSeeTableRecords([$zero])
            ->assertCanNotSeeTableRecords([$semData]);
    }

    public function test_filtra_por_checkbox_sim_nao_e_nao_incluindo_vazio(): void
    {
        $campo = CustomField::factory()->create(['name' => 'Garantia', 'key' => 'garantia', 'field_type' => FieldTypeEnum::CHECKBOX]);
        $this->cadastro->fields()->attach($campo->id, ['field_order' => 9]);

        $this->impressora->update(['data_json' => [...$this->impressora->data_json, 'garantia' => true]]);
        $this->notebook->update(['data_json' => [...$this->notebook->data_json, 'garantia' => false]]);
        $semValor = $this->registro(['modelo' => 'Sem garantia', 'garantia' => null]);

        $this->pagina()
            ->filterTable('field_'.$campo->id, ['value' => '1'])
            ->assertCanSeeTableRecords([$this->impressora])
            ->assertCanNotSeeTableRecords([$this->notebook, $semValor]);

        // "Não" inclui quem nunca marcou nem desmarcou
        $this->pagina()
            ->filterTable('field_'.$campo->id, ['value' => '0'])
            ->assertCanSeeTableRecords([$this->notebook, $semValor])
            ->assertCanNotSeeTableRecords([$this->impressora]);
    }

    public function test_valor_nao_vaza_por_filtro_ou_ordenacao_forjados(): void
    {
        $idPreco = 'field_'.$this->campo('preco')->id;

        // Filtro de faixa do campo escondido, enviado à mão: é ignorado (não vira consulta por tentativa)
        $this->pagina($this->executor)
            ->set('tableFilters', [$idPreco => ['min' => 1000, 'max' => null]])
            ->assertCanSeeTableRecords([$this->impressora, $this->notebook]);

        // Ordenação pelo campo escondido: ignorada, vale a ordem padrão (código)
        $porCodigo = CustomRecord::orderBy('code')->get();
        $this->pagina($this->executor)
            ->set('tableSortColumn', $idPreco)
            ->set('tableSortDirection', 'desc')
            ->assertSuccessful()
            ->assertCanSeeTableRecords($porCodigo, inOrder: true);
    }

    public function test_gestor_de_outro_cadastro_nao_ve_valor(): void
    {
        $outroGestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);
        $outroCadastro = CustomRecordType::create(['name' => 'Veículos', 'slug' => 'veiculos', 'is_system' => false]);
        $outroCadastro->managers()->attach($outroGestor->id);

        $idPreco = 'field_'.$this->campo('preco')->id;
        $pagina = $this->pagina($outroGestor);

        $this->assertNotContains($idPreco, array_keys($pagina->instance()->getTable()->getColumns()));
        $this->assertNotContains($idPreco, array_keys($pagina->instance()->getTable()->getFilters()));
        $this->assertStringNotContainsString('R$ 800,00', $this->conteudoDoCsv($outroGestor, []));
    }

    public function test_valor_de_cadastro_base_nao_aparece_para_executor(): void
    {
        $tipo = $this->tipoBase(Client::class);
        $campo = CustomField::factory()->create(['name' => 'Contrato', 'key' => 'contrato', 'field_type' => FieldTypeEnum::CURRENCY]);
        $tipo->fields()->attach($campo->id, ['field_order' => 0]);
        Client::create(['name' => 'Cliente Pago', 'is_active' => true, 'custom_data' => ['contrato' => '7777']]);

        $this->actingAs($this->executor);
        $pagina = Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->assertSee('Cliente Pago')
            ->assertDontSee('R$ 7.777,00')
            ->set('tableFilters', ['field_'.$campo->id => ['min' => 1, 'max' => null]]);

        $this->assertNotContains('field_'.$campo->id, array_keys($pagina->instance()->getTable()->getColumns()));

        $csv = base64_decode($pagina->callTableAction('export_csv')->effects['download']['content']);
        $this->assertStringContainsString('Cliente Pago', $csv);
        $this->assertStringNotContainsString('7.777', $csv);
        $this->assertStringNotContainsString('Contrato', $csv);

        // O admin continua vendo
        $this->actingAs($this->admin);
        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])->assertSee('R$ 7.777,00');
    }

    public function test_busca_e_ordenacao_nas_colunas_fixas_de_texto(): void
    {
        $beta = Client::create(['name' => 'Beta Ltda', 'is_active' => true]);
        $alfa = Client::create(['name' => 'Alfa SA', 'is_active' => true]);
        $tipo = $this->tipoBase(Client::class);

        $this->actingAs($this->admin);
        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->searchTable('Alfa')
            ->assertCanSeeTableRecords([$alfa])
            ->assertCanNotSeeTableRecords([$beta]);

        Livewire::test(CadastroReports::class, ['type_id' => $tipo->id])
            ->sortTable('name')
            ->assertCanSeeTableRecords([$alfa, $beta], inOrder: true)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$beta, $alfa], inOrder: true);
    }

    public function test_csv_com_barra_invertida_e_aspas_nao_quebra_a_coluna(): void
    {
        $this->registro(['modelo' => 'a\\"b', 'cidade' => 'Zeta']);

        $conteudo = $this->conteudoDoCsv($this->admin, $this->filtro('cidade', ['value' => 'Zeta']));
        $linhas = array_values(array_filter(explode("\n", ltrim($conteudo, "\xEF\xBB\xBF"))));

        $this->assertCount(2, $linhas);
        $cabecalho = str_getcsv($linhas[0], ';', '"', '');
        $linha = str_getcsv($linhas[1], ';', '"', '');
        $this->assertCount(count($cabecalho), $linha);
        $this->assertSame('a\\"b', $linha[array_search('Modelo', $cabecalho, true)]);
    }

    public function test_csv_neutraliza_formula_tambem_no_cabecalho(): void
    {
        $this->campo('cidade')->update(['name' => '=Cidade']);

        $this->assertStringContainsString("'=Cidade", $this->conteudoDoCsv($this->admin, []));
    }

    /**
     * Número de consultas da exportação, simulando requisição nova (resolvedor zerado).
     */
    private function consultasDaExportacao($pagina): int
    {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $pagina->callTableAction('export_csv');
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    public function test_exportacao_nao_faz_consulta_por_linha_no_cadastro_do_admin(): void
    {
        $fornecedor = Supplier::create(['name' => 'Fornecedor A']);
        $combo = CustomField::factory()->create([
            'name' => 'Fornecedor', 'key' => 'fornecedor', 'field_type' => FieldTypeEnum::COMBO,
            'options_json' => ['source' => 'supplier'],
        ]);
        $this->cadastro->fields()->attach($combo->id, ['field_order' => 9]);

        $criar = function (int $quantos) use ($fornecedor) {
            foreach (range(1, $quantos) as $i) {
                CustomRecord::create([
                    'custom_record_type_id' => $this->cadastro->id,
                    'created_by' => $i % 2 ? $this->admin->id : $this->gestor->id,
                    'data_json' => ['modelo' => "Item {$i}", 'fornecedor' => $fornecedor->id],
                ]);
            }
        };

        $criar(50);
        $com50 = $this->consultasDaExportacao($this->pagina());
        $criar(50);
        $com100 = $this->consultasDaExportacao($this->pagina());

        $this->assertSame($com50, $com100);
    }

    public function test_exportacao_nao_faz_consulta_por_linha_em_macroprocesso(): void
    {
        $tipo = $this->tipoBase(Macroprocess::class);
        $funcoes = ['Suportar Processos', 'Entregar Produtos e Serviços'];

        $criar = function (int $quantos) use ($funcoes) {
            foreach (range(1, $quantos) as $i) {
                $this->macro("Macro {$i}", $funcoes[$i % 2], $i % 2 ? $this->gestor : $this->admin);
            }
        };
        $pagina = function () use ($tipo) {
            $this->actingAs($this->admin);

            return Livewire::test(CadastroReports::class, ['type_id' => $tipo->id]);
        };

        $criar(50);
        $com50 = $this->consultasDaExportacao($pagina());
        $criar(50);
        $com100 = $this->consultasDaExportacao($pagina());

        $this->assertSame($com50, $com100);
    }

    private function conteudoDoCsv(User $usuario, array $filtros): string
    {
        $this->actingAs($usuario);

        $resposta = Livewire::test(CadastroReports::class, ['type_id' => $this->cadastro->id])
            ->set('tableFilters', $filtros)
            ->callTableAction('export_csv')
            ->effects['download'] ?? null;

        return base64_decode($resposta['content'] ?? '');
    }
}
