<?php

namespace Tests\Feature;

use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\RuleOperatorEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomFieldResource\Pages\CreateCustomField;
use App\Filament\Resources\CustomFieldResource\Pages\EditCustomField;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\User;
use App\Services\CustomFieldComponentFactory;
use App\Services\CustomFieldValueFormatter;
use App\Services\LookupResolver;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T4 — campo "Vínculo com cadastro" (FieldTypeEnum::COMBO): origem em
 * options_json, valor = UUID do registro, exibição pelo rótulo.
 */
class LookupFieldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
    }

    private function campo(array $config): CustomField
    {
        return CustomField::factory()->create([
            'name' => 'Vínculo',
            'key' => 'vinculo',
            'field_type' => FieldTypeEnum::COMBO,
            'options_json' => $config,
        ]);
    }

    private function cadastroEquipamentos(): array
    {
        $nome = CustomField::factory()->create(['name' => 'Nome', 'key' => 'nome', 'field_type' => FieldTypeEnum::TEXT]);
        $tipo = CustomRecordType::create(['name' => 'Equipamentos', 'slug' => 'equipamentos', 'is_system' => false]);
        $tipo->fields()->attach($nome->id, ['field_order' => 0]);

        $registro = CustomRecord::create([
            'custom_record_type_id' => $tipo->id,
            'created_by' => $this->admin->id,
            'data_json' => ['nome' => 'Impressora HP'],
        ]);

        return [$tipo, $registro];
    }

    public function test_resolver_busca_e_rotula_cliente(): void
    {
        $cliente = Client::create(['name' => 'Cliente Alfa', 'is_active' => true]);
        Client::create(['name' => 'Cliente Beta', 'is_active' => true]);
        $config = ['source' => 'client'];
        $resolver = new LookupResolver;

        $this->assertSame([$cliente->id => 'Cliente Alfa'], $resolver->search($config, 'Alfa'));
        $todos = $resolver->search($config, '');
        $this->assertContains('Cliente Alfa', $todos);
        $this->assertContains('Cliente Beta', $todos);
        $this->assertSame('Cliente Alfa', $resolver->label($config, $cliente->id));
    }

    public function test_resolver_de_registros_de_cadastro_usa_codigo_e_primeiro_campo(): void
    {
        [$tipo, $registro] = $this->cadastroEquipamentos();
        $config = ['source' => 'record_type', 'record_type_id' => $tipo->id];
        $esperado = "{$registro->code} - Impressora HP";

        $this->assertSame($esperado, (new LookupResolver)->label($config, $registro->id));
        $this->assertSame([$registro->id => $esperado], (new LookupResolver)->search($config, 'impressora'));
        $this->assertSame([], (new LookupResolver)->search($config, 'scanner'));
    }

    public function test_busca_em_registro_de_cadastro_ignora_caixa_do_termo(): void
    {
        [$tipo, $registro] = $this->cadastroEquipamentos();
        $registro->update(['data_json' => ['nome' => 'Notebook Dell']]);
        $config = ['source' => 'record_type', 'record_type_id' => $tipo->id];

        $this->assertCount(1, (new LookupResolver)->search($config, 'notebook'));
        $this->assertCount(1, (new LookupResolver)->search($config, 'NOTEBOOK'));
        $this->assertCount(1, (new LookupResolver)->search($config, 'bOOk De'));
        $this->assertSame([], (new LookupResolver)->search($config, 'scanner'));
    }

    public function test_origem_sem_configuracao_nao_quebra(): void
    {
        $this->assertSame([], (new LookupResolver)->search([], 'x'));
        $this->assertNull((new LookupResolver)->label(['source' => 'record_type'], 'uuid'));
    }

    public function test_formatter_mostra_rotulo_e_removido_quando_registro_some(): void
    {
        [$tipo, $registro] = $this->cadastroEquipamentos();
        $campo = $this->campo(['source' => 'record_type', 'record_type_id' => $tipo->id]);
        $formatter = new CustomFieldValueFormatter;

        $this->assertSame("{$registro->code} - Impressora HP", $formatter->format($campo, $registro->id));

        $registro->delete();

        $this->assertSame('(removido)', $formatter->format($campo, $registro->id));
        $this->assertSame('—', $formatter->format($campo, null));
    }

    public function test_formatter_mantem_moeda_e_lista(): void
    {
        $formatter = new CustomFieldValueFormatter;
        $moeda = CustomField::factory()->create(['field_type' => FieldTypeEnum::CURRENCY]);
        $texto = CustomField::factory()->create(['field_type' => FieldTypeEnum::TEXT]);

        $this->assertSame('R$ 1.500,50', $formatter->format($moeda, '1500.50'));
        $this->assertSame('a, b', $formatter->format($texto, '["a","b"]'));
        $this->assertSame('simples', $formatter->format($texto, 'simples'));
    }

    public function test_fabrica_gera_select_pesquisavel_sem_opcoes_estaticas(): void
    {
        $campo = $this->campo(['source' => 'client']);

        $componente = (new CustomFieldComponentFactory)->make($campo, 'data.vinculo');

        $this->assertInstanceOf(Select::class, $componente);
        $this->assertTrue($componente->isSearchable());
        $this->assertSame([], $componente->getOptions());
    }

    public function test_admin_cria_campo_de_vinculo_com_origem(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomField::class)
            ->fillForm([
                'name' => 'Cliente do chamado',
                'key' => 'cliente_do_chamado',
                'field_type' => FieldTypeEnum::COMBO->value,
                'lookup_source' => 'client',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campo = CustomField::where('key', 'cliente_do_chamado')->firstOrFail();
        $this->assertSame(['source' => 'client'], $campo->options_json);
    }

    public function test_origem_e_obrigatoria_e_cadastro_e_obrigatorio_para_registros(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomField::class)
            ->fillForm([
                'name' => 'Sem origem',
                'key' => 'sem_origem',
                'field_type' => FieldTypeEnum::COMBO->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['lookup_source' => 'required']);

        Livewire::test(CreateCustomField::class)
            ->fillForm([
                'name' => 'Sem cadastro',
                'key' => 'sem_cadastro',
                'field_type' => FieldTypeEnum::COMBO->value,
                'lookup_source' => 'record_type',
            ])
            ->call('create')
            ->assertHasFormErrors(['lookup_record_type_id' => 'required']);
    }

    public function test_vinculo_nao_oferece_operadores_de_regra(): void
    {
        $this->assertSame([], RuleOperatorEnum::forFieldType(FieldTypeEnum::COMBO));
        $this->assertSame([], RuleOperatorEnum::optionsForFieldType(FieldTypeEnum::COMBO));
    }

    public function test_demanda_salva_o_uuid_do_vinculo(): void
    {
        $cliente = Client::create(['name' => 'Cliente Vinculado', 'is_active' => true]);
        $campo = $this->campo(['source' => 'client']);

        $processo = CustomEntity::create([
            'name' => 'Processo Vínculo',
            'description' => 'Processo com vínculo',
            'is_active' => true,
            'sla_hours' => 24,
            'manager_id' => User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true])->id,
            'created_by' => $this->admin->id,
        ]);
        $processo->fields()->attach($campo->id, ['field_order' => 0]);
        $situacao = $processo->processStatuses()->where('system_key', 'new')->firstOrFail();
        FieldPermission::create([
            'entity_id' => $processo->id,
            'custom_field_id' => $campo->id,
            'process_status_id' => $situacao->id,
            'process_role_id' => null,
            'permission' => FieldPermissionEnum::OPTIONAL,
        ]);

        $demanda = Demand::factory()->create([
            'entity_id' => $processo->id,
            'client_id' => $cliente->id,
            'process_status_id' => $situacao->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'parent_demand_id' => null,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditDemand::class, ['record' => $demanda->id])
            ->set('data.field_data.vinculo', $cliente->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($cliente->id, DemandFieldValue::where('demand_id', $demanda->id)->value('value'));
    }

    public function test_editar_campo_de_vinculo_carrega_e_salva_a_origem(): void
    {
        $this->actingAs($this->admin);
        $campo = $this->campo(['source' => 'client']);

        Livewire::test(EditCustomField::class, ['record' => $campo->id])
            ->assertFormSet(['lookup_source' => 'client'])
            ->fillForm(['lookup_source' => 'user'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['source' => 'user'], $campo->fresh()->options_json);
    }

    private function demandaComCampo(CustomField $campo): Demand
    {
        $cliente = Client::create(['name' => 'Cliente Base', 'is_active' => true]);
        $processo = CustomEntity::create([
            'name' => 'Processo Teste',
            'description' => 'Processo de teste',
            'is_active' => true,
            'sla_hours' => 24,
            'manager_id' => User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true])->id,
            'created_by' => $this->admin->id,
        ]);
        $processo->fields()->attach($campo->id, ['field_order' => 0]);
        $situacao = $processo->processStatuses()->where('system_key', 'new')->firstOrFail();
        FieldPermission::create([
            'entity_id' => $processo->id,
            'custom_field_id' => $campo->id,
            'process_status_id' => $situacao->id,
            'process_role_id' => null,
            'permission' => FieldPermissionEnum::OPTIONAL,
        ]);

        return Demand::factory()->create([
            'entity_id' => $processo->id,
            'client_id' => $cliente->id,
            'process_status_id' => $situacao->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'parent_demand_id' => null,
        ]);
    }

    public function test_campo_em_uso_nao_troca_origem_nem_tipo(): void
    {
        $this->actingAs($this->admin);
        $campo = $this->campo(['source' => 'client']);
        $demanda = $this->demandaComCampo($campo);
        DemandFieldValue::create(['demand_id' => $demanda->id, 'custom_field_id' => $campo->id, 'value' => 'abc']);

        $this->assertTrue($campo->fresh()->hasStoredValues());

        Livewire::test(EditCustomField::class, ['record' => $campo->id])
            ->assertFormFieldIsDisabled('lookup_source')
            ->assertFormFieldIsDisabled('field_type')
            // mesmo forjando o payload, o servidor mantém tipo e origem
            ->set('data.lookup_source', 'user')
            ->set('data.field_type', FieldTypeEnum::TEXT->value)
            ->call('save');

        $campo = $campo->fresh();
        $this->assertSame(FieldTypeEnum::COMBO, $campo->field_type);
        $this->assertSame(['source' => 'client'], $campo->options_json);
    }

    public function test_edicao_nunca_altera_a_chave_do_campo(): void
    {
        $this->actingAs($this->admin);
        $campo = $this->campo(['source' => 'client']);

        Livewire::test(EditCustomField::class, ['record' => $campo->id])
            ->set('data.key', 'outra')
            ->call('save');

        $this->assertSame('vinculo', $campo->fresh()->key);
    }

    public function test_valor_em_registro_de_cadastro_excluido_tambem_bloqueia(): void
    {
        [$tipo, $registro] = $this->cadastroEquipamentos();
        $campo = $this->campo(['source' => 'client']);
        $tipo->fields()->attach($campo->id, ['field_order' => 1]);
        $registro->update(['data_json' => ['nome' => 'Impressora HP', 'vinculo' => 'abc']]);
        $registro->delete();

        $this->assertTrue($campo->fresh()->hasStoredValues());
    }

    public function test_valor_vazio_em_registro_de_cadastro_nao_bloqueia(): void
    {
        [$tipo, $registro] = $this->cadastroEquipamentos();
        $campo = $this->campo(['source' => 'client']);
        $tipo->fields()->attach($campo->id, ['field_order' => 1]);
        $registro->update(['data_json' => ['nome' => 'Impressora HP', 'vinculo' => '']]);

        $this->assertFalse($campo->fresh()->hasStoredValues());
    }

    public function test_campo_em_uso_em_registro_de_cadastro_tambem_bloqueia(): void
    {
        [$tipo, $registro] = $this->cadastroEquipamentos();
        $campo = $this->campo(['source' => 'client']);
        $tipo->fields()->attach($campo->id, ['field_order' => 1]);

        $this->assertFalse($campo->fresh()->hasStoredValues());

        $registro->update(['data_json' => ['nome' => 'Impressora HP', 'vinculo' => 'abc']]);

        $this->assertTrue($campo->fresh()->hasStoredValues());
    }

    public function test_campo_sem_valores_continua_livre_para_trocar_origem(): void
    {
        $this->actingAs($this->admin);
        $campo = $this->campo(['source' => 'client']);

        Livewire::test(EditCustomField::class, ['record' => $campo->id])
            ->assertFormFieldIsEnabled('lookup_source')
            ->assertFormFieldIsEnabled('field_type');
    }

    public function test_busca_em_cadastro_grande_acha_registro_alem_do_500_com_poucas_consultas(): void
    {
        [$tipo] = $this->cadastroEquipamentos();
        for ($i = 1; $i <= 520; $i++) {
            CustomRecord::create([
                'custom_record_type_id' => $tipo->id,
                'created_by' => $this->admin->id,
                'data_json' => ['nome' => $i === 520 ? 'Scanner Raro' : "Item {$i}"],
            ]);
        }
        $config = ['source' => 'record_type', 'record_type_id' => $tipo->id];

        $consultas = 0;
        \DB::listen(function () use (&$consultas) {
            $consultas++;
        });

        $achados = (new LookupResolver)->search($config, 'Scanner');

        $this->assertCount(1, $achados);
        $this->assertStringContainsString('Scanner Raro', array_values($achados)[0]);
        $this->assertLessThanOrEqual(5, $consultas);
        $this->assertCount(50, (new LookupResolver)->search($config, ''));
    }

    public function test_trocar_select_por_radio_preserva_opcoes(): void
    {
        $this->actingAs($this->admin);
        $campo = CustomField::factory()->create([
            'field_type' => FieldTypeEnum::SELECT,
            'options_json' => ['Baixa', 'Alta'],
        ]);

        Livewire::test(EditCustomField::class, ['record' => $campo->id])
            ->fillForm(['field_type' => FieldTypeEnum::RADIO->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Baixa', 'Alta'], $campo->fresh()->options_json);
        $this->assertSame(FieldTypeEnum::RADIO, $campo->fresh()->field_type);
    }

    public function test_combo_vira_texto_e_limpa_a_origem(): void
    {
        $this->actingAs($this->admin);
        $campo = $this->campo(['source' => 'client']);

        Livewire::test(EditCustomField::class, ['record' => $campo->id])
            ->fillForm(['field_type' => FieldTypeEnum::TEXT->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($campo->fresh()->options_json);
    }

    public function test_combo_nao_guarda_valor_padrao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomField::class)
            ->fillForm([
                'name' => 'Cliente padrão',
                'key' => 'cliente_padrao',
                'field_type' => FieldTypeEnum::COMBO->value,
                'lookup_source' => 'client',
                'default_value' => 'Impressora HP',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(CustomField::where('key', 'cliente_padrao')->firstOrFail()->default_value);
    }

    public function test_demanda_recusa_uuid_que_nao_pertence_a_origem(): void
    {
        $this->actingAs($this->admin);
        $campo = $this->campo(['source' => 'client']);
        $demanda = $this->demandaComCampo($campo);

        Livewire::test(EditDemand::class, ['record' => $demanda->id])
            ->set('data.field_data.vinculo', 'qualquer-coisa')
            ->call('save')
            ->assertHasFormErrors(['field_data.vinculo']);
    }

    public function test_demanda_aceita_valor_ja_gravado_mesmo_com_registro_removido(): void
    {
        $this->actingAs($this->admin);
        $cliente = Client::create(['name' => 'Cliente Some', 'is_active' => true]);
        $campo = $this->campo(['source' => 'client']);
        $demanda = $this->demandaComCampo($campo);
        DemandFieldValue::create(['demand_id' => $demanda->id, 'custom_field_id' => $campo->id, 'value' => $cliente->id]);
        $cliente->delete();

        Livewire::test(EditDemand::class, ['record' => $demanda->id])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_formatter_nao_quebra_com_json_aninhado(): void
    {
        $texto = CustomField::factory()->create(['field_type' => FieldTypeEnum::TEXTAREA]);

        $this->assertSame('{"a":{"b":1}}', (new CustomFieldValueFormatter)->format($texto, '{"a":{"b":1}}'));
    }

    public function test_busca_de_processo_so_traz_ativos(): void
    {
        $gestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);
        foreach ([['Processo Ativo', true], ['Processo Inativo', false]] as [$nome, $ativo]) {
            CustomEntity::create([
                'name' => $nome, 'description' => $nome, 'is_active' => $ativo,
                'sla_hours' => 24, 'manager_id' => $gestor->id, 'created_by' => $this->admin->id,
            ]);
        }

        $achados = (new LookupResolver)->search(['source' => 'process'], 'Processo');

        $this->assertCount(1, $achados);
    }
}
