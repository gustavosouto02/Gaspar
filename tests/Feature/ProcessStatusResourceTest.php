<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource\Pages\EditCustomEntity;
use App\Filament\Resources\CustomEntityResource\RelationManagers\ProcessStatusesRelationManager;
use App\Filament\Resources\ProcessStatusResource\Pages\CreateProcessStatus;
use App\Filament\Resources\ProcessStatusResource\Pages\EditProcessStatus;
use App\Filament\Resources\ProcessStatusResource\Pages\ListProcessStatuses;
use App\Models\CustomEntity;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcessStatusResourceTest extends TestCase
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

    public function test_formulario_de_situacao_possui_campo_descricao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProcessStatus::class)
            ->assertFormFieldExists('description')
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('color');
    }

    public function test_situacao_pode_ser_criada_com_descricao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProcessStatus::class)
            ->fillForm([
                'name' => 'Em Análise Técnica',
                'color' => 'blue',
                'description' => 'Demanda sob avaliação técnica da equipe responsável.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('process_statuses', [
            'name' => 'Em Análise Técnica',
            'color' => 'blue',
            'description' => 'Demanda sob avaliação técnica da equipe responsável.',
            'is_system' => false,
        ]);
    }

    public function test_situacao_pode_ser_criada_sem_descricao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProcessStatus::class)
            ->fillForm([
                'name' => 'Aguardando Fornecedor',
                'color' => 'yellow',
                'description' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('process_statuses', [
            'name' => 'Aguardando Fornecedor',
            'color' => 'yellow',
            'description' => null,
        ]);
    }

    public function test_descricao_da_situacao_pode_ser_editada(): void
    {
        $this->actingAs($this->admin);

        $status = ProcessStatus::create([
            'name' => 'Em Revisão',
            'color' => 'purple',
            'description' => 'Descrição antiga',
            'is_system' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(EditProcessStatus::class, ['record' => $status->id])
            ->fillForm([
                'description' => 'Nova descrição atualizada para a situação.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('process_statuses', [
            'id' => $status->id,
            'description' => 'Nova descrição atualizada para a situação.',
        ]);
    }

    public function test_situacoes_de_sistema_possuem_descricoes_padroes(): void
    {
        $this->assertDatabaseHas('process_statuses', [
            'system_key' => 'new',
            'description' => 'Situação inicial de toda demanda recém-aberta.',
        ]);

        $this->assertDatabaseHas('process_statuses', [
            'system_key' => 'closed',
            'description' => 'Situação final quando o atendimento da demanda foi concluído.',
        ]);

        $this->assertDatabaseHas('process_statuses', [
            'system_key' => 'evaluated',
            'description' => 'Situação final após o demandante avaliar a demanda concluída.',
        ]);

        $this->assertDatabaseHas('process_statuses', [
            'system_key' => 'canceled',
            'description' => 'Situação final quando a demanda é cancelada.',
        ]);

        $this->assertDatabaseHas('process_statuses', [
            'system_key' => 'conditional',
            'description' => 'Situação intermediária para avaliação e roteamento por regras de decisão.',
        ]);
    }

    public function test_coluna_descricao_existe_na_listagem_e_e_pesquisavel(): void
    {
        $this->actingAs($this->admin);

        $status = ProcessStatus::create([
            'name' => 'Situação Específica',
            'color' => 'green',
            'description' => 'TermoIncomumParaBusca',
            'is_system' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(ListProcessStatuses::class)
            ->assertTableColumnExists('description')
            ->searchTable('TermoIncomumParaBusca')
            ->assertCanSeeTableRecords([$status]);
    }

    public function test_coluna_descricao_existe_no_relation_manager_do_processo(): void
    {
        $this->actingAs($this->admin);

        $macro = Macroprocess::create([
            'name' => 'Macro Teste',
            'acronym' => 'MT',
            'value_chain_function' => 'Suportar Processos',
            'description' => 'Desc',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $entity = CustomEntity::create([
            'name' => 'Processo Teste Status',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Apoio',
            'sla_hours' => 24,
            'description' => 'Desc',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(ProcessStatusesRelationManager::class, [
            'ownerRecord' => $entity,
            'pageClass' => EditCustomEntity::class,
        ])
            ->assertTableColumnExists('description');
    }

    public function test_descricao_acima_de_1000_caracteres_falha_validacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProcessStatus::class)
            ->fillForm([
                'name' => 'Status Longo',
                'color' => 'blue',
                'description' => str_repeat('a', 1001),
            ])
            ->call('create')
            ->assertHasFormErrors(['description' => 'max']);
    }
}
