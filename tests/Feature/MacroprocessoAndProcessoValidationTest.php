<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource\Pages\CreateCustomEntity;
use App\Filament\Resources\CustomEntityResource\Pages\EditCustomEntity;
use App\Filament\Resources\CustomEntityResource\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\MembersRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\ProcessStatusesRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\StatusTransitionsRelationManager;
use App\Filament\Resources\MacroprocessResource\Pages\CreateMacroprocess;
use App\Models\CustomEntity;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MacroprocessoAndProcessoValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_campos_bases_de_macroprocesso_sao_obrigatorios_na_criacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMacroprocess::class)
            ->fillForm([
                'name' => '',
                'acronym' => '',
                'value_chain_function' => null,
                'description' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'acronym' => 'required',
                'value_chain_function' => 'required',
                'description' => 'required',
            ]);
    }

    public function test_macroprocesso_eh_criado_com_sucesso_quando_campos_bases_sao_preenchidos(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMacroprocess::class)
            ->fillForm([
                'name' => 'Gestão de Suprimentos',
                'acronym' => 'SUPRI',
                'value_chain_function' => 'Suportar Processos',
                'description' => 'Macroprocesso responsável pela aquisição e distribuição de insumos.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('macroprocesses', [
            'name' => 'Gestão de Suprimentos',
            'acronym' => 'SUPRI',
            'value_chain_function' => 'Suportar Processos',
        ]);
    }

    public function test_campos_bases_de_processo_sao_obrigatorios_na_criacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomEntity::class)
            ->fillForm([
                'name' => '',
                'macroprocess_id' => null,
                'purpose' => null,
                'sla_hours' => null,
                'description' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'macroprocess_id' => 'required',
                'purpose' => 'required',
                'sla_hours' => 'required',
                'description' => 'required',
            ]);
    }

    public function test_processo_eh_criado_com_sucesso_quando_campos_bases_sao_preenchidos(): void
    {
        $this->actingAs($this->admin);

        $macro = Macroprocess::create([
            'name' => 'Macroprocesso Teste',
            'acronym' => 'TEST',
            'value_chain_function' => 'Apoio',
            'description' => 'Descrição do macroprocesso teste',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(CreateCustomEntity::class)
            ->fillForm([
                'name' => 'Compra Direta de Pequeno Valor',
                'macroprocess_id' => $macro->id,
                'purpose' => 'Apoio',
                'sla_hours' => 72,
                'description' => 'Fluxo de aquisições até o limite de dispensa.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_entities', [
            'name' => 'Compra Direta de Pequeno Valor',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Apoio',
            'sla_hours' => 72,
        ]);
    }

    public function test_aba_papeis_no_processo_possui_acao_vincular_papel(): void
    {
        $this->actingAs($this->admin);

        $macro = Macroprocess::create([
            'name' => 'Macroprocesso Teste',
            'acronym' => 'TEST',
            'value_chain_function' => 'Apoio',
            'description' => 'Descrição do macroprocesso teste',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $entity = CustomEntity::create([
            'name' => 'Processo Exemplo',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Finalístico',
            'sla_hours' => 48,
            'description' => 'Descrição do processo',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(MembersRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->assertTableActionExists('create')
            ->assertSee('Vincular Papel');

        Livewire::test(FieldsRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->assertTableActionExists('attach')
            ->assertSee('Vincular Campo');

        Livewire::test(ProcessStatusesRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->assertTableActionExists('attach')
            ->assertSee('Vincular Situação');

        Livewire::test(StatusTransitionsRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->assertTableActionExists('create')
            ->assertSee('Adicionar Transição');

        $this->assertEquals(
            'Fluxo do Processo',
            StatusTransitionsRelationManager::getTitle($entity, EditCustomEntity::class)
        );
    }

    public function test_campo_papeis_que_podem_disparar_eh_obrigatorio_no_formulario_de_transicao(): void
    {
        $this->actingAs($this->admin);

        $macro = Macroprocess::create([
            'name' => 'Macroprocesso Teste 2',
            'acronym' => 'TEST2',
            'value_chain_function' => 'Apoio',
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $entity = CustomEntity::create([
            'name' => 'Processo Exemplo 2',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Finalístico',
            'sla_hours' => 48,
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $status = ProcessStatus::create([
            'name' => 'Em Análise Teste',
            'color' => 'blue',
            'is_system' => false,
        ]);
        $entity->processStatuses()->attach($status->id, ['display_order' => 1]);

        Livewire::test(StatusTransitionsRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->callTableAction('create', data: [
                'to_status_id' => $status->id,
                'label' => DemandButtonEnum::ENVIAR->value,
                'allowed_role_ids' => [],
            ])
            ->assertHasTableActionErrors(['allowed_role_ids' => 'required']);
    }

    public function test_campo_label_na_transicao_deve_ser_salvar_ou_enviar_e_rejeita_customizado(): void
    {
        $macro = Macroprocess::create([
            'name' => 'Macroprocesso Teste Enum',
            'acronym' => 'MTE',
            'value_chain_function' => 'Finalístico',
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $entity = CustomEntity::create([
            'name' => 'Processo Teste Enum',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Finalístico',
            'sla_hours' => 24,
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $status = ProcessStatus::create([
            'name' => 'Em Análise Enum',
            'color' => 'blue',
            'is_system' => false,
        ]);
        $entity->processStatuses()->attach($status->id, ['display_order' => 1]);

        // Rejeita nome customizado como 'Aprovar' ou 'Customizado'
        Livewire::test(StatusTransitionsRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->callTableAction('create', data: [
                'to_status_id' => $status->id,
                'label' => 'Nome Customizado Invalido',
                'allowed_role_ids' => ['__requester__'],
            ])
            ->assertHasTableActionErrors(['label']);

        // Aceita 'Salvar' e 'Enviar'
        Livewire::test(StatusTransitionsRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->callTableAction('create', data: [
                'to_status_id' => $status->id,
                'label' => DemandButtonEnum::SALVAR->value,
                'allowed_role_ids' => ['__requester__'],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('status_transitions', [
            'entity_id' => $entity->id,
            'to_status_id' => $status->id,
            'label' => 'Salvar',
        ]);
    }
}
