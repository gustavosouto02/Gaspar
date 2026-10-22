<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\SatisfactionRatingEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use App\Services\DemandTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CanceledAndEvaluatedStatusRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $requester;
    private User $otherUser;
    private CustomEntity $entity;
    private Client $client;
    private ProcessStatus $statusNova;
    private ProcessStatus $statusEncerrada;
    private ProcessStatus $statusAvaliada;
    private ProcessStatus $statusCancelada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $this->requester = User::factory()->create([
            'user_role' => UserRoleEnum::EXECUTOR,
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'user_role' => UserRoleEnum::EXECUTOR,
            'is_active' => true,
        ]);

        $macro = Macroprocess::create([
            'name'       => 'Macro Suporte',
            'acronym'    => 'SUP',
            'is_active'  => true,
            'created_by' => $this->admin->id,
        ]);

        $this->entity = CustomEntity::create([
            'name'            => 'Processo Regras Especiais',
            'macroprocess_id' => $macro->id,
            'is_active'       => true,
            'created_by'      => $this->admin->id,
        ]);

        $this->client = Client::create([
            'name'      => 'Cliente Teste',
            'is_active' => true,
        ]);

        $this->statusNova      = ProcessStatus::findBySystemKey('new');
        $this->statusEncerrada = ProcessStatus::findBySystemKey('closed');
        $this->statusAvaliada  = ProcessStatus::findBySystemKey('evaluated');
        $this->statusCancelada = ProcessStatus::findBySystemKey('canceled');
    }

    public function test_process_flow_scopes_exclude_canceled_and_evaluated_statuses(): void
    {
        // Origem de transição (from): não deve incluir nem condicional, nem cancelada, nem avaliada
        $fromOptions = $this->entity->processStatuses()->withoutSpecialSystemStatuses()->pluck('system_key')->toArray();
        $this->assertNotContains('conditional', $fromOptions);
        $this->assertNotContains('canceled', $fromOptions);
        $this->assertNotContains('evaluated', $fromOptions);
        $this->assertContains('new', $fromOptions);
        $this->assertContains('closed', $fromOptions);

        // Destino de transição (to): não deve incluir cancelada nem avaliada
        $toOptions = $this->entity->processStatuses()->withoutUnmanualTransitions()->pluck('system_key')->toArray();
        $this->assertNotContains('canceled', $toOptions);
        $this->assertNotContains('evaluated', $toOptions);
        $this->assertContains('closed', $toOptions);
    }

    public function test_status_transition_cannot_be_saved_pointing_to_canceled_or_evaluated(): void
    {
        // Tentar salvar transição com to_status apontando para Cancelada
        $this->expectException(ValidationException::class);

        StatusTransition::create([
            'entity_id'        => $this->entity->id,
            'from_status_id'   => $this->statusNova->id,
            'to_status_id'     => $this->statusCancelada->id,
            'label'            => DemandButtonEnum::ENVIAR->value,
            'allowed_role_ids' => ['__requester__'],
            'display_order'    => 1,
        ]);
    }

    public function test_status_transition_cannot_be_saved_pointing_to_evaluated(): void
    {
        $this->expectException(ValidationException::class);

        StatusTransition::create([
            'entity_id'        => $this->entity->id,
            'from_status_id'   => $this->statusEncerrada->id,
            'to_status_id'     => $this->statusAvaliada->id,
            'label'            => DemandButtonEnum::SALVAR->value,
            'allowed_role_ids' => ['__requester__'],
            'display_order'    => 1,
        ]);
    }

    public function test_evaluating_a_demand_automatically_transitions_to_evaluated_status(): void
    {
        $demand = Demand::create([
            'title'             => 'Demanda para Avaliação',
            'entity_id'         => $this->entity->id,
            'process_status_id' => $this->statusEncerrada->id,
            'client_id'         => $this->client->id,
            'requested_by'      => $this->requester->id,
            'created_by'        => $this->requester->id,
            'status'            => DemandStatusEnum::CLOSED,
        ]);

        $this->assertEquals($this->statusEncerrada->id, $demand->process_status_id);
        $this->assertNull($demand->satisfaction_rating);

        // O demandante avalia o atendimento
        $demand->update([
            'satisfaction_rating'  => SatisfactionRatingEnum::GREAT,
            'satisfaction_comment' => 'Atendimento excelente!',
        ]);

        $demand->refresh();

        // Transição automática para Avaliada
        $this->assertEquals($this->statusAvaliada->id, $demand->process_status_id);
        $this->assertEquals(DemandStatusEnum::EVALUATED, $demand->status);
        $this->assertNotNull($demand->satisfaction_evaluated_at);
        $this->assertEquals(SatisfactionRatingEnum::GREAT, $demand->satisfaction_rating);
    }

    public function test_demand_transition_service_blocks_manual_transition_to_evaluated(): void
    {
        $transition = new StatusTransition([
            'entity_id'      => $this->entity->id,
            'from_status_id' => $this->statusEncerrada->id,
            'to_status_id'   => $this->statusAvaliada->id,
            'label'          => DemandButtonEnum::SALVAR->value,
        ]);
        // Salva sem eventos de validação para simular tentativa forçada
        $transition->saveQuietly();

        $demand = Demand::create([
            'title'             => 'Demanda Teste Bloqueio',
            'entity_id'         => $this->entity->id,
            'process_status_id' => $this->statusEncerrada->id,
            'client_id'         => $this->client->id,
            'requested_by'      => $this->requester->id,
            'created_by'        => $this->requester->id,
            'status'            => DemandStatusEnum::CLOSED,
        ]);

        $service = app(DemandTransitionService::class);

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessage('A situação "Avaliada" é atribuída automaticamente');

        $service->execute($demand, $transition, $this->admin);
    }

    public function test_evaluating_a_demand_that_is_not_closed_does_not_transition_to_evaluated(): void
    {
        $demand = Demand::create([
            'title'             => 'Demanda Aberta',
            'entity_id'         => $this->entity->id,
            'process_status_id' => $this->statusNova->id,
            'client_id'         => $this->client->id,
            'requested_by'      => $this->requester->id,
            'created_by'        => $this->requester->id,
            'status'            => DemandStatusEnum::ACTIVE,
        ]);

        $demand->update([
            'satisfaction_rating' => SatisfactionRatingEnum::GREAT,
        ]);

        $demand->refresh();

        // Não deve transicionar para Avaliada se a demanda não estava encerrada
        $this->assertEquals($this->statusNova->id, $demand->process_status_id);
        $this->assertEquals(DemandStatusEnum::ACTIVE, $demand->status);
    }

    public function test_unauthorized_user_cannot_cancel_demand_via_service(): void
    {
        $transition = new StatusTransition([
            'entity_id'      => $this->entity->id,
            'from_status_id' => $this->statusNova->id,
            'to_status_id'   => $this->statusCancelada->id,
            'label'          => DemandButtonEnum::ENVIAR->value,
        ]);
        $transition->saveQuietly();

        $demand = Demand::create([
            'title'             => 'Demanda Teste Cancelamento Não Autorizado',
            'entity_id'         => $this->entity->id,
            'process_status_id' => $this->statusNova->id,
            'client_id'         => $this->client->id,
            'requested_by'      => $this->requester->id,
            'created_by'        => $this->requester->id,
            'status'            => DemandStatusEnum::ACTIVE,
        ]);

        $service = app(DemandTransitionService::class);

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessage('Apenas o demandante ou um administrador podem cancelar esta demanda.');

        $service->execute($demand, $transition, $this->otherUser);
    }

    public function test_requester_and_admin_can_cancel_demand_via_service(): void
    {
        $transition = new StatusTransition([
            'entity_id'      => $this->entity->id,
            'from_status_id' => $this->statusNova->id,
            'to_status_id'   => $this->statusCancelada->id,
            'label'          => DemandButtonEnum::ENVIAR->value,
        ]);
        $transition->saveQuietly();

        $demandRequester = Demand::create([
            'title'             => 'Demanda Cancelada Pelo Demandante',
            'entity_id'         => $this->entity->id,
            'process_status_id' => $this->statusNova->id,
            'client_id'         => $this->client->id,
            'requested_by'      => $this->requester->id,
            'created_by'        => $this->requester->id,
            'status'            => DemandStatusEnum::ACTIVE,
        ]);

        $demandAdmin = Demand::create([
            'title'             => 'Demanda Cancelada Pelo Admin',
            'entity_id'         => $this->entity->id,
            'process_status_id' => $this->statusNova->id,
            'client_id'         => $this->client->id,
            'requested_by'      => $this->requester->id,
            'created_by'        => $this->requester->id,
            'status'            => DemandStatusEnum::ACTIVE,
        ]);

        $service = app(DemandTransitionService::class);

        // Demandante cancela sua própria demanda
        $service->execute($demandRequester, $transition, $this->requester);
        $demandRequester->refresh();
        $this->assertEquals($this->statusCancelada->id, $demandRequester->process_status_id);

        // Admin cancela demanda de outro usuário
        $service->execute($demandAdmin, $transition, $this->admin);
        $demandAdmin->refresh();
        $this->assertEquals($this->statusCancelada->id, $demandAdmin->process_status_id);
    }
}
