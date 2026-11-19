<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AquiProcessSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InternalClientSeededTest extends TestCase
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

    public function test_cliente_interno_eh_criado_automaticamente_pela_migration(): void
    {
        $this->assertDatabaseHas('clients', [
            'name' => 'Interno',
            'email' => 'interno@gaspar.local',
            'is_active' => true,
        ]);

        $client = Client::where('name', 'Interno')->first();
        $this->assertNotNull($client);
        $this->assertTrue($client->is_active);
        $this->assertMatchesRegularExpression('/^CLI-\d{4}-\d{4}$/', $client->code);
    }

    public function test_cliente_interno_aparece_para_selecao_no_formulario_de_demandas(): void
    {
        $this->actingAs($this->admin);

        $client = Client::where('name', 'Interno')->firstOrFail();

        Livewire::test(CreateDemand::class)
            ->assertFormFieldExists('client_id', fn (Select $field): bool => array_key_exists($client->id, $field->getOptions()));
    }

    public function test_cliente_interno_aparece_para_selecao_no_formulario_de_projetos(): void
    {
        $this->actingAs($this->admin);

        $client = Client::where('name', 'Interno')->firstOrFail();

        Livewire::test(CreateProject::class)
            ->assertFormFieldExists('client_id', fn (Select $field): bool => array_key_exists($client->id, $field->getOptions()));
    }

    public function test_seeder_mantem_cliente_interno_sem_duplicar(): void
    {
        $this->seed(AquiProcessSeeder::class);

        $this->assertSame(1, Client::where('name', 'Interno')->count());
        $this->assertDatabaseHas('clients', [
            'name' => 'Interno',
            'is_active' => true,
        ]);
    }

    public function test_down_remove_cliente_interno_quando_nao_utilizado(): void
    {
        $client = Client::where('name', 'Interno')->first();
        $this->assertNotNull($client);

        $migration = require database_path('migrations/2026_11_17_160000_insert_default_internal_client.php');
        $migration->down();

        $this->assertDatabaseMissing('clients', ['name' => 'Interno']);
    }
}
