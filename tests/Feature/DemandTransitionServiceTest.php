<?php

namespace Tests\Feature;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\ActivityLog;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use App\Models\User;
use App\Services\DemandTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemandTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemandTransitionService $service;

    private CustomEntity $entity;

    private ProcessStatus $newStatus;

    private ProcessStatus $conditionalStatus;

    private ProcessStatus $approvalStatus;

    private ProcessStatus $validationStatus;

    private CustomField $valueField;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->service = app(DemandTransitionService::class);

        // O hook created do processo anexa as situações de sistema (inclusive a Condicional)
        $this->entity = CustomEntity::factory()->create();
        $this->newStatus = ProcessStatus::findBySystemKey('new');
        $this->conditionalStatus = ProcessStatus::findBySystemKey('conditional');

        $this->approvalStatus = ProcessStatus::factory()->create(['name' => 'Em aprovação']);
        $this->validationStatus = ProcessStatus::factory()->create(['name' => 'Em validação']);
        $this->entity->processStatuses()->attach($this->approvalStatus->id, ['display_order' => 10]);
        $this->entity->processStatuses()->attach($this->validationStatus->id, ['display_order' => 11]);

        $this->valueField = CustomField::factory()->create([
            'name' => 'Valor',
            'field_type' => FieldTypeEnum::NUMBER,
        ]);
        $this->entity->fields()->attach($this->valueField->id, ['field_order' => 0]);

        $this->user = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR]);
    }

    private function makeDemand(?string $value = null): Demand
    {
        $demand = Demand::factory()->create([
            'entity_id' => $this->entity->id,
            'process_status_id' => $this->newStatus->id,
        ]);

        if ($value !== null) {
            DemandFieldValue::create([
                'demand_id' => $demand->id,
                'custom_field_id' => $this->valueField->id,
                'value' => $value,
            ]);
        }

        return $demand;
    }

    /**
     * Transição Nova -> Condicional com a regra "Valor > 1000 → Em aprovação"
     * e senão "Em validação"
     */
    private function makeConditionalTransition(): StatusTransition
    {
        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'from_status_id' => $this->newStatus->id,
            'to_status_id' => $this->conditionalStatus->id,
            'default_to_status_id' => $this->validationStatus->id,
            'label' => 'Encaminhar',
        ]);

        TransitionRule::create([
            'status_transition_id' => $transition->id,
            'rule_order' => 0,
            'conditions' => [
                ['field_id' => $this->valueField->id, 'operator' => 'gt', 'value' => '1000'],
            ],
            'to_status_id' => $this->approvalStatus->id,
        ]);

        return $transition;
    }

    private function lastTransitionLog(Demand $demand): ?ActivityLog
    {
        return ActivityLog::where('auditable_type', Demand::class)
            ->where('auditable_id', (string) $demand->id)
            ->where('event', 'transition')
            ->orderByDesc('created_at')
            ->first();
    }

    public function test_gateway_resolves_to_matched_rule_status(): void
    {
        $demand = $this->makeDemand('1500');
        $transition = $this->makeConditionalTransition();

        $target = $this->service->execute($demand, $transition, $this->user);

        $this->assertSame($this->approvalStatus->id, $target->id);
        // A demanda nunca fica gravada com a situação Condicional
        $this->assertSame($this->approvalStatus->id, $demand->fresh()->process_status_id);

        $log = $this->lastTransitionLog($demand);
        $this->assertNotNull($log);
        $this->assertSame('Condicional', $log->new_values['via'] ?? null);
        $this->assertSame(0, $log->new_values['rule_order'] ?? null);
        $this->assertSame($this->approvalStatus->id, $log->new_values['process_status_id'] ?? null);
    }

    public function test_gateway_falls_back_to_default_status(): void
    {
        $demand = $this->makeDemand('500');
        $transition = $this->makeConditionalTransition();

        $target = $this->service->execute($demand, $transition, $this->user);

        $this->assertSame($this->validationStatus->id, $target->id);
        $this->assertSame($this->validationStatus->id, $demand->fresh()->process_status_id);

        $log = $this->lastTransitionLog($demand);
        $this->assertSame('Condicional', $log->new_values['via'] ?? null);
        $this->assertNull($log->new_values['rule_order']);
    }

    public function test_gateway_blocks_when_rule_input_field_is_empty(): void
    {
        $demand = $this->makeDemand(null);
        $transition = $this->makeConditionalTransition();

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessageMatches('/Valor/');

        $this->service->execute($demand, $transition, $this->user);
    }

    public function test_gateway_applies_subdemand_lock_on_resolved_closed_status(): void
    {
        $closedStatus = ProcessStatus::findBySystemKey('closed');

        $demand = $this->makeDemand('1500');
        Demand::factory()->create([
            'entity_id' => $this->entity->id,
            'process_status_id' => $this->newStatus->id,
            'parent_demand_id' => $demand->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);

        $transition = $this->makeConditionalTransition();
        // A regra que casa passa a resolver para "Encerrada"
        $transition->rules()->first()->update(['to_status_id' => $closedStatus->id]);

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessageMatches('/subdemandas abertas/');

        $this->service->execute($demand, $transition, $this->user);

        $this->assertSame($this->newStatus->id, $demand->fresh()->process_status_id);
    }

    public function test_transition_blocks_when_required_field_is_empty(): void
    {
        $demand = $this->makeDemand(null);

        // "Valor" é obrigatório na situação atual (Nova) pela matriz de permissões
        FieldPermission::create([
            'entity_id' => $this->entity->id,
            'custom_field_id' => $this->valueField->id,
            'process_status_id' => $this->newStatus->id,
            'process_role_id' => null,
            'permission' => FieldPermissionEnum::REQUIRED,
        ]);

        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'from_status_id' => $this->newStatus->id,
            'to_status_id' => $this->approvalStatus->id,
            'label' => 'Aprovar',
        ]);

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessageMatches('/Valor/');

        try {
            $this->service->execute($demand, $transition, $this->user);
        } catch (DemandTransitionException $e) {
            // A situação da demanda não pode ter mudado
            $this->assertSame($this->newStatus->id, $demand->fresh()->process_status_id);
            throw $e;
        }
    }

    public function test_simple_transition_still_works(): void
    {
        $demand = $this->makeDemand('100');

        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'from_status_id' => $this->newStatus->id,
            'to_status_id' => $this->approvalStatus->id,
            'label' => 'Aprovar',
        ]);

        $target = $this->service->execute($demand, $transition, $this->user);

        $this->assertSame($this->approvalStatus->id, $target->id);
        $this->assertSame($this->approvalStatus->id, $demand->fresh()->process_status_id);

        $log = $this->lastTransitionLog($demand);
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('via', $log->new_values);
    }
}
