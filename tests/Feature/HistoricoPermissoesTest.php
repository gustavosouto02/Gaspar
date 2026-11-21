<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O histórico de valores e o estado do formulário respeitam a matriz de
 * permissões (HIDDEN) e a regra de visibilidade de moeda, como PDF e relatório.
 */
class HistoricoPermissoesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $executor;

    private User $gestor;

    private CustomEntity $processo;

    private ProcessStatus $situacao;

    private Demand $demanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->executor = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);
        $this->gestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        $this->processo = CustomEntity::create([
            'name' => 'Processo Permissões',
            'description' => 'Processo de teste',
            'is_active' => true,
            'sla_hours' => 24,
            'manager_id' => $this->gestor->id,
            'created_by' => $this->admin->id,
        ]);
        $this->situacao = $this->processo->processStatuses()->where('system_key', 'new')->firstOrFail();
        $outra = ProcessStatus::factory()->create(['created_by' => $this->admin->id]);

        // O executor responsável dispara uma transição, então a política deixa editar
        StatusTransition::create([
            'entity_id' => $this->processo->id,
            'from_status_id' => $this->situacao->id,
            'to_status_id' => $outra->id,
            'label' => DemandButtonEnum::SALVAR->value,
            'allowed_role_ids' => ['__assignee__'],
            'display_order' => 1,
        ]);

        $this->demanda = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'client_id' => Client::create(['name' => 'Cliente Perm', 'is_active' => true])->id,
            'process_status_id' => $this->situacao->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->executor->id,
            'parent_demand_id' => null,
        ]);
    }

    private function campo(string $nome, string $key, FieldTypeEnum $tipo, FieldPermissionEnum $permissao, ?string $valor = null): CustomField
    {
        $campo = CustomField::factory()->create(['name' => $nome, 'key' => $key, 'field_type' => $tipo]);
        $this->processo->fields()->attach($campo->id, ['field_order' => 0]);
        FieldPermission::create([
            'entity_id' => $this->processo->id,
            'custom_field_id' => $campo->id,
            'process_status_id' => $this->situacao->id,
            'process_role_id' => null,
            'permission' => $permissao,
        ]);

        if ($valor !== null) {
            DemandFieldValue::create(['demand_id' => $this->demanda->id, 'custom_field_id' => $campo->id, 'value' => $valor]);
        }

        return $campo;
    }

    private function valorGravado(CustomField $campo): ?string
    {
        return DemandFieldValue::where('demand_id', $this->demanda->id)
            ->where('custom_field_id', $campo->id)
            ->value('value');
    }

    public function test_campo_oculto_desvinculado_nao_aparece_no_historico_para_executor(): void
    {
        $parecer = $this->campo('Parecer interno', 'parecer', FieldTypeEnum::TEXT, FieldPermissionEnum::HIDDEN, 'segredo-xyz');
        $this->processo->fields()->detach($parecer->id);

        $this->actingAs($this->executor);

        foreach ([ViewDemand::class, EditDemand::class] as $pagina) {
            Livewire::test($pagina, ['record' => $this->demanda->id])
                ->assertDontSee('Parecer interno')
                ->assertDontSee('segredo-xyz');
        }

        // quem não está sujeito ao HIDDEN (ADMIN) continua vendo o histórico
        $this->actingAs($this->admin);
        foreach ([ViewDemand::class, EditDemand::class] as $pagina) {
            Livewire::test($pagina, ['record' => $this->demanda->id])
                ->assertSee('Parecer interno (fora do formulário atual)')
                ->assertSee('segredo-xyz');
        }
    }

    public function test_campo_de_moeda_excluido_so_aparece_para_quem_pode_ver_moeda(): void
    {
        $preco = $this->campo('Preço negociado', 'preco', FieldTypeEnum::CURRENCY, FieldPermissionEnum::OPTIONAL, '12345.67');
        $preco->delete();

        $this->actingAs($this->executor);
        foreach ([ViewDemand::class, EditDemand::class] as $pagina) {
            Livewire::test($pagina, ['record' => $this->demanda->id])
                ->assertDontSee('Preço negociado')
                ->assertDontSee('12.345,67');
        }

        $this->actingAs($this->admin);
        foreach ([ViewDemand::class, EditDemand::class] as $pagina) {
            Livewire::test($pagina, ['record' => $this->demanda->id])
                ->assertSee('Preço negociado (fora do formulário atual)')
                ->assertSee('12.345,67');
        }
    }
}
