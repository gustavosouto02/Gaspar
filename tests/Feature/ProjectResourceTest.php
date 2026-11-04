<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Models\ProcessStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectResourceTest extends TestCase
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

    public function test_campo_processo_nao_esta_presente_no_formulario_de_projetos(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProject::class)
            ->assertFormFieldDoesNotExist('entity_id')
            ->assertFormFieldExists('process_status_id')
            ->assertFormFieldExists('name', fn ($field) => $field->getLabel() === 'Nome do projeto:');
    }

    public function test_projeto_pode_ser_criado_sem_processo(): void
    {
        $this->actingAs($this->admin);

        $status = ProcessStatus::create([
            'name' => 'Em Planejamento',
            'color' => 'blue',
            'is_system' => false,
        ]);

        $client = Client::create(['name' => 'Cliente Teste']);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'Projeto Novo Portal',
                'process_status_id' => $status->id,
                'client_id' => $client->id,
                'contact_name' => $this->admin->name,
                'manager_name' => $this->admin->name,
                'sponsor_name' => $this->admin->name,
                'scrum_master_name' => $this->admin->name,
                'product_owner_name' => $this->admin->name,
                'budget' => 1000,
                'description' => 'Descrição do projeto',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', [
            'name' => 'Projeto Novo Portal',
            'process_status_id' => $status->id,
            'entity_id' => null,
        ]);
    }

    public function test_campos_primarios_sao_obrigatorios_no_cadastro_de_projetos(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Projeto incompleto'])
            ->call('create')
            ->assertHasFormErrors([
                'client_id' => 'required',
                'contact_name' => 'required',
                'manager_name' => 'required',
                'sponsor_name' => 'required',
                'scrum_master_name' => 'required',
                'product_owner_name' => 'required',
                'budget' => 'required',
                'description' => 'required',
            ]);
    }
}
