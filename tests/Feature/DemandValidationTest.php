<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemandValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private CustomEntity $entity;
    private ProcessStatus $status;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $macro = Macroprocess::create([
            'name' => 'Macroprocesso Demandas',
            'acronym' => 'DEM',
            'value_chain_function' => 'Finalístico',
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->entity = CustomEntity::create([
            'name' => 'Fluxo de Atendimento',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Finalístico',
            'sla_hours' => 24,
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->status = ProcessStatus::where('system_key', 'new')->first()
            ?? ProcessStatus::create([
                'name' => 'Nova',
                'color' => 'blue',
                'system_key' => 'new',
                'is_system' => false,
            ]);
        $this->entity->processStatuses()->syncWithoutDetaching([$this->status->id => ['display_order' => 1]]);

        $this->client = Client::create([
            'name' => 'Cliente Teste INCT',
            'email' => 'cliente@inct.com',
            'is_active' => true,
        ]);
    }

    public function test_campo_cliente_eh_obrigatorio_na_criacao_de_demanda(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'title' => 'Demanda sem cliente',
                'client_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['client_id' => 'required']);
    }

    public function test_demanda_eh_criada_com_sucesso_quando_cliente_eh_informado(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'title' => 'Demanda com cliente preenchido',
                'client_id' => $this->client->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('demands', [
            'title' => 'Demanda com cliente preenchido',
            'client_id' => $this->client->id,
            'entity_id' => $this->entity->id,
        ]);
    }
}
