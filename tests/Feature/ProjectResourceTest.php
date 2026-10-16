<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Models\ProcessStatus;
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
            ->assertFormFieldExists('name');
    }

    public function test_projeto_pode_ser_criado_sem_processo(): void
    {
        $this->actingAs($this->admin);

        $status = ProcessStatus::create([
            'name' => 'Em Planejamento',
            'color' => 'blue',
            'is_system' => false,
        ]);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'Projeto Novo Portal',
                'process_status_id' => $status->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', [
            'name' => 'Projeto Novo Portal',
            'process_status_id' => $status->id,
            'entity_id' => null,
        ]);
    }
}
