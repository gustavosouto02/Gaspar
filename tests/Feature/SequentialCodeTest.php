<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Demand;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequentialCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private CustomEntity $entity;
    private ProcessStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $macro = Macroprocess::create([
            'name' => 'Macro Teste',
            'acronym' => 'MT',
            'is_active' => true,
        ]);

        $this->entity = CustomEntity::create([
            'macroprocess_id' => $macro->id,
            'name' => 'Processo Sequencial',
            'is_active' => true,
            'created_by' => $this->user->id,
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
            'name' => 'Cliente Teste',
            'is_active' => true,
        ]);
    }

    public function test_demands_receive_sequential_code_automatically(): void
    {
        $year = date('Y');

        $demand1 = Demand::create([
            'title' => 'Primeira Demanda',
            'entity_id' => $this->entity->id,
            'process_status_id' => $this->status->id,
            'client_id' => $this->client->id,
            'requested_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $demand2 = Demand::create([
            'title' => 'Segunda Demanda',
            'entity_id' => $this->entity->id,
            'process_status_id' => $this->status->id,
            'client_id' => $this->client->id,
            'requested_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals("DEM-{$year}-0001", $demand1->code);
        $this->assertEquals("DEM-{$year}-0002", $demand2->code);
    }

    public function test_projects_receive_sequential_number_automatically(): void
    {
        $year = date('Y');

        $project1 = Project::create([
            'name' => 'Projeto Alpha',
            'client_id' => $this->client->id,
        ]);

        $project2 = Project::create([
            'name' => 'Projeto Beta',
            'client_id' => $this->client->id,
        ]);

        $this->assertEquals("PRJ-{$year}-0001", $project1->number);
        $this->assertEquals("PRJ-{$year}-0002", $project2->number);
    }

    public function test_custom_records_receive_sequential_code_automatically(): void
    {
        $year = date('Y');

        $recordType = CustomRecordType::firstOrCreate(
            ['slug' => 'fornecedores-teste'],
            ['name' => 'Fornecedores Teste', 'is_active' => true]
        );

        $record1 = CustomRecord::create([
            'custom_record_type_id' => $recordType->id,
            'created_by' => $this->user->id,
            'data_json' => ['nome' => 'Fornecedor A'],
        ]);

        $record2 = CustomRecord::create([
            'custom_record_type_id' => $recordType->id,
            'created_by' => $this->user->id,
            'data_json' => ['nome' => 'Fornecedor B'],
        ]);

        $this->assertEquals("REC-{$year}-0001", $record1->code);
        $this->assertEquals("REC-{$year}-0002", $record2->code);
    }

    public function test_manual_code_or_number_is_not_overwritten(): void
    {
        $project = Project::create([
            'name' => 'Projeto Custom',
            'number' => 'CUSTOM-999',
            'client_id' => $this->client->id,
        ]);

        $this->assertEquals('CUSTOM-999', $project->number);
    }

    public function test_demand_scope_global_search_finds_by_code(): void
    {
        $demand = Demand::create([
            'title' => 'Demanda Para Busca',
            'entity_id' => $this->entity->id,
            'process_status_id' => $this->status->id,
            'client_id' => $this->client->id,
            'requested_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // Busca pelo código exato
        $foundExact = Demand::globalSearch($demand->code)->get();
        $this->assertTrue($foundExact->contains('id', $demand->id));

        // Busca com cerquilha '#' na frente (comportamento comum de usuários)
        $foundHash = Demand::globalSearch('#' . $demand->code)->get();
        $this->assertTrue($foundHash->contains('id', $demand->id));

        // Busca pelo sufixo numérico
        $foundSeq = Demand::globalSearch('0001')->get();
        $this->assertTrue($foundSeq->contains('id', $demand->id));
    }

    public function test_demand_scope_global_search_finds_by_project_number(): void
    {
        $project = Project::create([
            'name' => 'Projeto Especial',
            'client_id' => $this->client->id,
        ]);

        $demand = Demand::create([
            'title' => 'Demanda de Projeto',
            'entity_id' => $this->entity->id,
            'process_status_id' => $this->status->id,
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'requested_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // Busca pelo número do projeto
        $found = Demand::globalSearch($project->number)->get();
        $this->assertTrue($found->contains('id', $demand->id));
    }

    public function test_sequence_is_strictly_incremental_even_after_records_are_deleted(): void
    {
        $year = date('Y');

        // Cria o primeiro projeto
        $project1 = Project::create([
            'name' => 'Projeto Inicial',
            'client_id' => $this->client->id,
        ]);
        $this->assertEquals("PRJ-{$year}-0001", $project1->number);

        // Exclui fisicamente o projeto do banco
        $project1->delete();

        // Cria um novo projeto e garante que ele NÃO reutiliza 0001, mas gera 0002
        $project2 = Project::create([
            'name' => 'Novo Projeto Após Exclusão',
            'client_id' => $this->client->id,
        ]);
        $this->assertEquals("PRJ-{$year}-0002", $project2->number);

        // Exclui o segundo também
        $project2->delete();

        // O terceiro deve ser 0003
        $project3 = Project::create([
            'name' => 'Terceiro Projeto',
            'client_id' => $this->client->id,
        ]);
        $this->assertEquals("PRJ-{$year}-0003", $project3->number);
    }
}
