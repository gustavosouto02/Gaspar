<?php

namespace Tests\Feature;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\ProcessStatus;
use App\Models\User;
use Database\Seeders\AquiProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Situação, status e autoria da demanda são definidos pelo sistema e pelo fluxo:
 * o criar/salvar da tela não os aceita de fora.
 */
class DemandProtectedAttributesTest extends TestCase
{
    use RefreshDatabase;

    private CustomEntity $processo;

    private User $demandante;

    private User $outro;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seed(AquiProcessSeeder::class);

        $this->processo = CustomEntity::where('name', 'AQUI - Contratação de serviços avulsos')->firstOrFail();
        $this->demandante = User::where('email', 'adinilson.martins@inct.org.br')->firstOrFail();
        $this->outro = User::where('email', 'adinilson.martins@gmail.com')->firstOrFail();
        $this->actingAs($this->demandante);
    }

    public function test_salvar_demanda_enviada_ignora_atributos_do_sistema(): void
    {
        $nova = ProcessStatus::findBySystemKey('new');
        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'client_id' => Client::first()->id,
            'process_status_id' => $nova->id,
            'requested_by' => $this->demandante->id,
            'created_by' => $this->demandante->id,
            'status' => DemandStatusEnum::ACTIVE,
            'parent_demand_id' => null,
        ]);
        $original = $demand->fresh()->only(['entity_id', 'process_status_id', 'status', 'priority', 'requested_by', 'created_by', 'parent_demand_id']);
        $mae = Demand::factory()->create(['entity_id' => $this->processo->id]);

        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->set('data.title', 'Título novo')
            ->set('data.process_status_id', ProcessStatus::findBySystemKey('closed')->id)
            ->set('data.status', DemandStatusEnum::CLOSED->value)
            ->set('data.priority', 'URGENT')
            ->set('data.requested_by', $this->outro->id)
            ->set('data.created_by', $this->outro->id)
            ->set('data.parent_demand_id', $mae->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $demand->refresh();
        $this->assertSame('Título novo', $demand->title);
        $this->assertEquals($original, $demand->only(array_keys($original)));
    }

    public function test_nova_demanda_nasce_na_situacao_inicial_e_em_nome_de_quem_salva(): void
    {
        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->processo->id,
                'client_id' => Client::first()->id,
                'title' => 'Criação conferida',
                'project_id' => 'none',
                'supplier_id' => 'none',
            ])
            ->set('data.process_status_id', ProcessStatus::findBySystemKey('closed')->id)
            ->set('data.requested_by', $this->outro->id)
            ->set('data.priority', 'URGENT')
            ->callAction('salvar');

        $demand = Demand::where('title', 'Criação conferida')->firstOrFail();
        $this->assertSame(ProcessStatus::findBySystemKey('new')->id, $demand->process_status_id);
        $this->assertSame($this->demandante->id, $demand->requested_by);
        $this->assertSame(DemandPriorityEnum::MEDIUM, $demand->priority);
    }

    public static function maesRecusadas(): array
    {
        return [
            'inexistente' => ['inexistente'],
            'finalizada' => ['finalizada'],
            'já é subdemanda' => ['subdemanda'],
        ];
    }

    #[DataProvider('maesRecusadas')]
    public function test_subdemanda_exige_demanda_principal_valida(string $caso): void
    {
        $raiz = $this->demandaPrincipal();
        $mae = match ($caso) {
            'inexistente' => (string) Str::uuid7(),
            'finalizada' => $this->demandaPrincipal(['status' => DemandStatusEnum::CLOSED])->id,
            'subdemanda' => $this->demandaPrincipal(['parent_demand_id' => $raiz->id])->id,
        };

        $this->criarSubdemanda($mae)->assertHasFormErrors(['parent_demand_id']);

        $this->assertFalse(Demand::where('title', 'Filha')->exists());
    }

    public function test_subdemanda_sob_demanda_em_andamento_eh_aceita(): void
    {
        $mae = $this->demandaPrincipal();

        $this->criarSubdemanda($mae->id)->assertHasNoFormErrors();

        $this->assertSame($mae->id, Demand::where('title', 'Filha')->firstOrFail()->parent_demand_id);
    }

    private function demandaPrincipal(array $atributos = []): Demand
    {
        return Demand::factory()->create($atributos + [
            'entity_id' => $this->processo->id,
            'client_id' => Client::first()->id,
            'process_status_id' => ProcessStatus::findBySystemKey('new')->id,
            'status' => DemandStatusEnum::ACTIVE,
            'parent_demand_id' => null,
        ]);
    }

    private function criarSubdemanda(string $maeId): Testable
    {
        return Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->processo->id,
                'client_id' => Client::first()->id,
                'title' => 'Filha',
                'project_id' => 'none',
                'supplier_id' => 'none',
            ])
            ->set('data.parent_demand_id', $maeId)
            ->call('create');
    }
}
