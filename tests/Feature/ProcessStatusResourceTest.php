<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\ProcessStatusResource\Pages\CreateProcessStatus;
use App\Filament\Resources\ProcessStatusResource\Pages\EditProcessStatus;
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
                'color' => 'amber',
                'description' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('process_statuses', [
            'name' => 'Aguardando Fornecedor',
            'color' => 'amber',
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
}

