<?php

namespace Tests\Feature;

use App\Enums\DemandStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\DemandComment;
use App\Models\DemandFieldValue;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use App\Notifications\DemandActivityNotification;
use App\Services\DemandTransitionService;
use Database\Seeders\AquiProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessoAquiE2ETest extends TestCase
{
    use RefreshDatabase;

    private CustomEntity $processo;
    private User $admin;
    private User $demandante;
    private User $licitador;
    private User $aprovador;
    private User $aprovadorAlto;
    private User $adquirente;
    private User $quitador;

    protected function setUp(): void
    {
        parent::setUp();

        // Roda o seeder do processo AQUI
        $this->seed(AquiProcessSeeder::class);

        $this->processo = CustomEntity::where('name', 'AQUI - Contratação de serviços avulsos')->firstOrFail();
        $this->admin = User::where('email', 'admin@gaspar.com')->firstOrFail();
        $this->demandante = User::where('email', 'adinilson.martins@inct.org.br')->firstOrFail();
        $this->licitador = User::where('email', 'adinilson.martins@gmail.com')->firstOrFail();
        $this->aprovador = User::where('email', 'nil@estudandomais.com.br')->firstOrFail();
        $this->aprovadorAlto = User::where('email', 'adinilson.martins@inct.org')->firstOrFail();
        $this->adquirente = User::where('email', 'nil@pierconsultoria.com.br')->firstOrFail();
        $this->quitador = User::where('email', 'plataforma@inct.org.br')->firstOrFail();
    }

    public function test_gateway_licitacao_roteia_para_aprovando_alto_quando_preco_maior_que_10000(): void
    {
        $statusNova = ProcessStatus::where('system_key', 'new')->firstOrFail();
        $statusLicitando = ProcessStatus::where('name', 'Licitando')->firstOrFail();
        $statusAprovandoAlto = ProcessStatus::where('name', 'Aprovando alto')->firstOrFail();

        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusLicitando->id,
            'requested_by' => $this->demandante->id,
            'assigned_to' => $this->licitador->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        $campoPreco = $this->processo->fields()->where('key', 'preco')->firstOrFail();
        DemandFieldValue::create([
            'demand_id' => $demand->id,
            'custom_field_id' => $campoPreco->id,
            'value' => '15000.00',
        ]);

        $transition = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $statusLicitando->id)
            ->firstOrFail();

        $service = app(DemandTransitionService::class);
        $novoStatus = $service->execute($demand, $transition, $this->licitador);

        $this->assertEquals($statusAprovandoAlto->id, $novoStatus->id);
        $this->assertEquals($this->aprovadorAlto->id, $demand->fresh()->assigned_to);
    }

    public function test_gateway_licitacao_roteia_para_aprovando_quando_preco_entre_2000_e_10000(): void
    {
        $statusLicitando = ProcessStatus::where('name', 'Licitando')->firstOrFail();
        $statusAprovando = ProcessStatus::where('name', 'Aprovando')->firstOrFail();

        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusLicitando->id,
            'requested_by' => $this->demandante->id,
            'assigned_to' => $this->licitador->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        $campoPreco = $this->processo->fields()->where('key', 'preco')->firstOrFail();
        DemandFieldValue::create([
            'demand_id' => $demand->id,
            'custom_field_id' => $campoPreco->id,
            'value' => '4500.00',
        ]);

        $transition = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $statusLicitando->id)
            ->firstOrFail();

        $service = app(DemandTransitionService::class);
        $novoStatus = $service->execute($demand, $transition, $this->licitador);

        $this->assertEquals($statusAprovando->id, $novoStatus->id);
        $this->assertEquals($this->aprovador->id, $demand->fresh()->assigned_to);
    }

    public function test_gateway_licitacao_roteia_para_adquirindo_quando_preco_ate_2000(): void
    {
        $statusLicitando = ProcessStatus::where('name', 'Licitando')->firstOrFail();
        $statusAdquirindo = ProcessStatus::where('name', 'Adquirindo')->firstOrFail();

        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusLicitando->id,
            'requested_by' => $this->demandante->id,
            'assigned_to' => $this->licitador->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        $campoPreco = $this->processo->fields()->where('key', 'preco')->firstOrFail();
        DemandFieldValue::create([
            'demand_id' => $demand->id,
            'custom_field_id' => $campoPreco->id,
            'value' => '1200.00',
        ]);

        $transition = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $statusLicitando->id)
            ->firstOrFail();

        $service = app(DemandTransitionService::class);
        $novoStatus = $service->execute($demand, $transition, $this->licitador);

        $this->assertEquals($statusAdquirindo->id, $novoStatus->id);
        $this->assertEquals($this->adquirente->id, $demand->fresh()->assigned_to);
    }

    public function test_gateway_aprovacao_reprovada_retorna_para_licitando(): void
    {
        $statusAprovando = ProcessStatus::where('name', 'Aprovando')->firstOrFail();
        $statusLicitando = ProcessStatus::where('name', 'Licitando')->firstOrFail();

        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusAprovando->id,
            'requested_by' => $this->demandante->id,
            'assigned_to' => $this->aprovador->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        $campoAprovacao = $this->processo->fields()->where('key', 'aprovacao')->firstOrFail();
        DemandFieldValue::create([
            'demand_id' => $demand->id,
            'custom_field_id' => $campoAprovacao->id,
            'value' => 'Não aprovado',
        ]);

        $transition = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $statusAprovando->id)
            ->firstOrFail();

        $service = app(DemandTransitionService::class);
        $novoStatus = $service->execute($demand, $transition, $this->aprovador);

        $this->assertEquals($statusLicitando->id, $novoStatus->id);
        $this->assertEquals($this->licitador->id, $demand->fresh()->assigned_to);
    }

    public function test_notificacao_eh_enviada_ao_adicionar_comentario(): void
    {
        Notification::fake();

        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'requested_by' => $this->demandante->id,
            'assigned_to' => $this->licitador->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        DemandComment::create([
            'demand_id' => $demand->id,
            'user_id' => $this->demandante->id,
            'comment' => 'Olá, preciso de urgência nesta licitação.',
        ]);

        // O responsável (Manoel) deve receber notificação
        Notification::assertSentTo($this->licitador, DemandActivityNotification::class);
        // O solicitante (José) não deve receber pois foi o autor do comentário
        Notification::assertNotSentTo($this->demandante, DemandActivityNotification::class);
    }

    public function test_rotas_administrativas_sao_bloqueadas_para_usuarios_nao_admin(): void
    {
        $response = $this->post('/update-system');
        $response->assertStatus(403);

        $response = $this->post('/trigger-deadlines');
        $response->assertStatus(403);

        $response = $this->post('/test-email');
        $response->assertStatus(403);

        $this->actingAs($this->demandante);
        $response = $this->post('/update-system');
        $response->assertStatus(403);

        $this->actingAs($this->admin);
        $response = $this->post('/test-email');
        $this->assertTrue(in_array($response->status(), [200, 500])); // Autenticado, passa pelo middleware (retorna json)
    }

    public function test_bloqueia_conclusao_quando_possui_subdemandas_pendentes(): void
    {
        $statusQuitando = ProcessStatus::where('name', 'Quitando')->firstOrFail();
        $statusEncerrada = ProcessStatus::where('system_key', 'closed')->firstOrFail();

        $demandMae = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusQuitando->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        // Cria subdemanda pendente
        $subdemanda = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'parent_demand_id' => $demandMae->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        $this->assertFalse($demandMae->canBeCompleted());

        $transition = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $statusQuitando->id)
            ->firstOrFail();

        $this->expectException(\App\Exceptions\DemandTransitionException::class);
        app(DemandTransitionService::class)->execute($demandMae, $transition, $this->quitador);
    }

    public function test_demanda_encerrada_aguardando_avaliacao_aparece_no_painel_do_demandante(): void
    {
        $statusEncerrada = ProcessStatus::where('system_key', 'closed')->firstOrFail();

        $demanda = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusEncerrada->id,
            'requested_by' => $this->demandante->id,
            'assigned_to' => $this->quitador->id,
            'status' => DemandStatusEnum::CLOSED,
            'satisfaction_rating' => null,
            'completed_at' => now(),
        ]);

        // Para o solicitante (José), a demanda DEVE constar como pendência de ação dele (avaliação de atendimento)
        $this->assertTrue(
            Demand::query()->pendingActionForUser($this->demandante)->where('id', $demanda->id)->exists()
        );

        // Para outro usuário (licitador), ela NÃO deve constar como pendência
        $this->assertFalse(
            Demand::query()->pendingActionForUser($this->licitador)->where('id', $demanda->id)->exists()
        );

        // Testa que o widget MyDemandsWidget exibe a demanda no painel para o demandante
        $this->actingAs($this->demandante);
        \Livewire\Livewire::test(\App\Filament\Widgets\MyDemandsWidget::class)
            ->assertSee($demanda->title)
            ->assertSee('Aguardando sua avaliação de atendimento');

        // Após José avaliar a demanda, ela é concluída e NÃO aparece mais como pendente
        $demanda->update([
            'satisfaction_rating' => \App\Enums\SatisfactionRatingEnum::GREAT->value,
            'satisfaction_comment' => 'Excelente atendimento e agilidade!',
            'satisfaction_evaluated_at' => now(),
            'status' => DemandStatusEnum::EVALUATED,
        ]);

        $this->assertFalse(
            Demand::query()->pendingActionForUser($this->demandante)->where('id', $demanda->id)->exists()
        );
    }

    public function test_prazo_sla_e_inicio_atendimento_sao_calculados_e_exibidos_no_pdf(): void
    {
        $statusNova = ProcessStatus::where('system_key', 'new')->firstOrFail();
        $statusLicitando = ProcessStatus::where('name', 'Licitando')->firstOrFail();

        // 1. Demanda criada vinculada ao processo (que tem 120h de SLA)
        $demanda = Demand::create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $statusNova->id,
            'requested_by' => $this->demandante->id,
            'created_by' => $this->demandante->id,
            'title' => 'Demanda Teste SLA',
            'status' => DemandStatusEnum::DRAFT,
        ]);

        // SLA deve ter sido calculado automaticamente (120 horas)
        $this->assertNotNull($demanda->sla_due_at);
        $this->assertEquals(
            now()->addHours(120)->format('d/m/Y'),
            $demanda->sla_due_at->format('d/m/Y')
        );

        // 2. Transição para a primeira situação de atendimento (Licitando)
        $transition = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $statusNova->id)
            ->where('to_status_id', $statusLicitando->id)
            ->firstOrFail();

        app(DemandTransitionService::class)->execute($demanda, $transition, $this->demandante);
        $demanda->refresh();

        $this->assertNotNull($demanda->started_at);
        $this->assertEquals(DemandStatusEnum::ACTIVE, $demanda->status);

        // 3. Verifica que a rota de PDF exibe o documento com sucesso sem 'Sem prazo'
        $this->actingAs($this->demandante);
        $response = $this->get(route('demands.pdf', ['record' => $demanda->id]));
        $response->assertStatus(200);
        $response->assertDontSee('Sem prazo');
    }

    public function test_submissao_rascunho_em_edit_demand_calcula_sla_mesmo_com_entity_id_disabled(): void
    {
        $statusNova = ProcessStatus::where('system_key', 'new')->firstOrFail();

        $demanda = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'client_id' => \App\Models\Client::first()?->id,
            'process_status_id' => $statusNova->id,
            'requested_by' => $this->demandante->id,
            'created_by' => $this->demandante->id,
            'title' => 'Rascunho sem SLA preenchido',
            'status' => DemandStatusEnum::DRAFT,
            'sla_due_at' => null,
        ]);

        $this->actingAs($this->demandante);

        \Livewire\Livewire::test(\App\Filament\Resources\DemandResource\Pages\EditDemand::class, ['record' => $demanda->id])
            ->call('submitDraft')
            ->assertHasNoErrors();

        $demanda->refresh();
        $this->assertEquals(DemandStatusEnum::ACTIVE, $demanda->status);
        $this->assertNotNull($demanda->sla_due_at);
    }
}
