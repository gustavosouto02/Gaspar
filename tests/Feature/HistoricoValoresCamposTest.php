<?php

namespace Tests\Feature;

use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Pages\DemandReports;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Histórico dos valores: campo desvinculado do processo ou excluído continua
 * guardando o que a demanda preencheu, e aparece (somente leitura) na demanda,
 * no relatório e no PDF.
 */
class HistoricoValoresCamposTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomEntity $processo;

    private CustomField $campoAtual;

    private CustomField $campoAntigo;

    private Demand $demanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->actingAs($this->admin);

        $this->campoAtual = CustomField::factory()->create(['name' => 'Campo Atual', 'key' => 'campo_atual', 'field_type' => FieldTypeEnum::TEXT]);
        $this->campoAntigo = CustomField::factory()->create(['name' => 'Campo Antigo', 'key' => 'campo_antigo', 'field_type' => FieldTypeEnum::TEXT]);

        $this->processo = CustomEntity::create([
            'name' => 'Processo Histórico',
            'description' => 'Processo de teste',
            'is_active' => true,
            'sla_hours' => 24,
            'manager_id' => User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true])->id,
            'created_by' => $this->admin->id,
        ]);
        $this->processo->fields()->attach($this->campoAtual->id, ['field_order' => 0]);
        $this->processo->fields()->attach($this->campoAntigo->id, ['field_order' => 1]);

        $situacao = $this->processo->processStatuses()->where('system_key', 'new')->firstOrFail();
        foreach ([$this->campoAtual, $this->campoAntigo] as $campo) {
            FieldPermission::create([
                'entity_id' => $this->processo->id,
                'custom_field_id' => $campo->id,
                'process_status_id' => $situacao->id,
                'process_role_id' => null,
                'permission' => FieldPermissionEnum::OPTIONAL,
            ]);
        }

        $this->demanda = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'client_id' => Client::create(['name' => 'Cliente Hist', 'is_active' => true])->id,
            'process_status_id' => $situacao->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'parent_demand_id' => null,
        ]);
        DemandFieldValue::create(['demand_id' => $this->demanda->id, 'custom_field_id' => $this->campoAtual->id, 'value' => 'valor atual']);
        DemandFieldValue::create(['demand_id' => $this->demanda->id, 'custom_field_id' => $this->campoAntigo->id, 'value' => 'valor da época']);
    }

    private function valorGravado(CustomField $campo): ?string
    {
        return DemandFieldValue::where('demand_id', $this->demanda->id)
            ->where('custom_field_id', $campo->id)
            ->value('value');
    }

    public function test_salvar_demanda_mantem_valor_de_campo_desvinculado(): void
    {
        $this->processo->fields()->detach($this->campoAntigo->id);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->set('data.field_data.campo_atual', 'valor editado')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('valor editado', $this->valorGravado($this->campoAtual));
        $this->assertSame('valor da época', $this->valorGravado($this->campoAntigo));
    }

    public function test_demanda_mostra_campo_desvinculado_somente_leitura_com_o_valor_da_epoca(): void
    {
        $this->processo->fields()->detach($this->campoAntigo->id);

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->assertFormSet(['field_data.campo_antigo' => 'valor da época'])
            ->assertFormFieldIsDisabled('field_data.campo_antigo')
            ->assertFormFieldIsEnabled('field_data.campo_atual');

        Livewire::test(ViewDemand::class, ['record' => $this->demanda->id])
            ->assertFormSet(['field_data.campo_antigo' => 'valor da época']);
    }

    public function test_campo_excluido_continua_aparecendo_na_demanda_e_mantem_o_valor(): void
    {
        $this->campoAntigo->delete();

        $this->assertSame('valor da época', $this->valorGravado($this->campoAntigo));

        Livewire::test(EditDemand::class, ['record' => $this->demanda->id])
            ->assertFormSet(['field_data.campo_antigo' => 'valor da época'])
            ->assertFormFieldIsDisabled('field_data.campo_antigo')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('valor da época', $this->valorGravado($this->campoAntigo));
    }

    public function test_pdf_traz_campo_desvinculado_e_excluido_com_marca_de_historico(): void
    {
        $this->processo->fields()->detach($this->campoAntigo->id);

        $this->get(route('demands.pdf', $this->demanda->id))->assertOk();
        // O PDF é binário; confere a view renderizada com os mesmos dados do controller
        $demanda = Demand::with(['entity.macroprocess', 'fieldValues.customField'])->find($this->demanda->id);
        $render = fn () => view('pdf.demand-report', [
            'demand' => $demanda,
            'transitionsHistory' => collect(),
            'canViewMoney' => true,
        ])->render();

        $desvinculado = $render();
        $this->assertStringContainsString('Campo Antigo', $desvinculado);
        $this->assertStringContainsString('valor da época', $desvinculado);
        $this->assertStringContainsString('fora do formulário atual', $desvinculado);

        $this->campoAntigo->delete();
        $demanda = Demand::with(['entity.macroprocess', 'fieldValues.customField'])->find($this->demanda->id);

        $excluido = $render();
        $this->assertStringContainsString('Campo Antigo', $excluido);
        $this->assertStringContainsString('valor da época', $excluido);
    }

    public function test_relatorio_tem_coluna_de_campo_desvinculado_e_excluido(): void
    {
        $this->processo->fields()->detach($this->campoAntigo->id);

        $colunas = function () {
            $componente = Livewire::test(DemandReports::class)
                ->set('tableFilters.entity_id.value', $this->processo->id)
                ->call('$refresh');

            return $componente->instance()->getTable()->getColumns();
        };

        $nome = 'field_'.$this->campoAntigo->id;
        $this->assertArrayHasKey($nome, $colunas());
        $this->assertStringContainsString('fora do formulário atual', $colunas()[$nome]->getLabel());
        $this->assertSame(
            'valor da época',
            $colunas()[$nome]->record(Demand::with('fieldValues')->find($this->demanda->id))->getState()
        );

        $this->campoAntigo->delete();
        $this->assertArrayHasKey($nome, $colunas());
    }

    public function test_campo_com_valores_nao_pode_ser_excluido_definitivamente(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->campoAntigo->forceDelete();
    }

    public function test_campo_sem_valores_pode_ser_excluido_definitivamente(): void
    {
        $semUso = CustomField::factory()->create(['key' => 'sem_uso', 'field_type' => FieldTypeEnum::TEXT]);

        $semUso->forceDelete();

        $this->assertNull(CustomField::withTrashed()->find($semUso->id));
    }
}
