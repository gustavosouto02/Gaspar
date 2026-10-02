<?php

namespace Tests\Unit;

use App\Enums\FieldTypeEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use App\Services\TransitionDecisionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionDecisionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private TransitionDecisionEvaluator $evaluator;

    private CustomEntity $entity;

    private ProcessStatus $matchStatus;

    private ProcessStatus $defaultStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new TransitionDecisionEvaluator();
        $this->entity = CustomEntity::factory()->create();
        $this->matchStatus = ProcessStatus::factory()->create(['name' => 'Em aprovação']);
        $this->defaultStatus = ProcessStatus::factory()->create(['name' => 'Em validação']);
    }

    /**
     * Cria demanda + campo com valor + transição com uma regra de condição única
     * e resolve: casou → matchStatus; não casou → defaultStatus ("senão").
     */
    private function resolveSingleCondition(FieldTypeEnum $type, string $operator, mixed $conditionValue, ?string $demandValue): array
    {
        $field = CustomField::factory()->create(['field_type' => $type]);
        $demand = Demand::factory()->create(['entity_id' => $this->entity->id]);

        if ($demandValue !== null) {
            DemandFieldValue::create([
                'demand_id' => $demand->id,
                'custom_field_id' => $field->id,
                'value' => $demandValue,
            ]);
        }

        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'default_to_status_id' => $this->defaultStatus->id,
        ]);

        TransitionRule::create([
            'status_transition_id' => $transition->id,
            'rule_order' => 0,
            'conditions' => [
                ['field_id' => $field->id, 'operator' => $operator, 'value' => $conditionValue],
            ],
            'to_status_id' => $this->matchStatus->id,
        ]);

        $decision = $this->evaluator->resolve($transition, $demand);

        return [$decision, $field];
    }

    private function assertMatches(FieldTypeEnum $type, string $operator, mixed $conditionValue, ?string $demandValue): void
    {
        [$decision] = $this->resolveSingleCondition($type, $operator, $conditionValue, $demandValue);

        $this->assertSame($this->matchStatus->id, $decision->toStatusId, "Esperava casar: {$operator} " . json_encode($conditionValue) . " com \"{$demandValue}\"");
        $this->assertNotNull($decision->matchedRule);
    }

    private function assertDoesNotMatch(FieldTypeEnum $type, string $operator, mixed $conditionValue, ?string $demandValue): void
    {
        [$decision] = $this->resolveSingleCondition($type, $operator, $conditionValue, $demandValue);

        $this->assertSame($this->defaultStatus->id, $decision->toStatusId, "Esperava NÃO casar: {$operator} " . json_encode($conditionValue) . " com \"{$demandValue}\"");
        $this->assertNull($decision->matchedRule);
    }

    // ---- NUMBER ----

    public function test_number_equals(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'eq', '1000', '1000');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'eq', '1000', '999');
    }

    public function test_number_not_equals(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'neq', '1000', '999');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'neq', '1000', '1000');
    }

    public function test_number_greater(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'gt', '1000', '1500');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'gt', '1000', '1000');
    }

    public function test_number_greater_or_equal(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'gte', '1000', '1000');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'gte', '1000', '999.99');
    }

    public function test_number_less(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'lt', '1000', '500');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'lt', '1000', '1000');
    }

    public function test_number_less_or_equal(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'lte', '1000', '1000');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'lte', '1000', '1000.01');
    }

    public function test_number_between_is_inclusive(): void
    {
        $between = ['min' => '10', 'max' => '20'];

        $this->assertMatches(FieldTypeEnum::NUMBER, 'between', $between, '10');
        $this->assertMatches(FieldTypeEnum::NUMBER, 'between', $between, '20');
        $this->assertMatches(FieldTypeEnum::NUMBER, 'between', $between, '15');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'between', $between, '9.99');
        $this->assertDoesNotMatch(FieldTypeEnum::NUMBER, 'between', $between, '20.01');
    }

    public function test_number_accepts_brazilian_decimal_format(): void
    {
        $this->assertMatches(FieldTypeEnum::NUMBER, 'eq', '1000.5', '1.000,50');
        $this->assertMatches(FieldTypeEnum::NUMBER, 'gt', '1000', '1.500,00');
    }

    // ---- DATE ----

    public function test_date_equals_compares_by_day(): void
    {
        $this->assertMatches(FieldTypeEnum::DATE, 'eq', '2026-09-19', '2026-09-19');
        $this->assertDoesNotMatch(FieldTypeEnum::DATE, 'eq', '2026-09-19', '2026-09-20');
    }

    public function test_date_comparison_operators(): void
    {
        $this->assertMatches(FieldTypeEnum::DATE, 'gt', '2026-09-19', '2026-09-20');
        $this->assertMatches(FieldTypeEnum::DATE, 'gte', '2026-09-19', '2026-09-19');
        $this->assertMatches(FieldTypeEnum::DATE, 'lt', '2026-09-19', '2026-09-18');
        $this->assertMatches(FieldTypeEnum::DATE, 'lte', '2026-09-19', '2026-09-19');
        $this->assertDoesNotMatch(FieldTypeEnum::DATE, 'gt', '2026-09-19', '2026-09-19');
    }

    public function test_date_between_is_inclusive(): void
    {
        $between = ['min' => '2026-01-01', 'max' => '2026-12-31'];

        $this->assertMatches(FieldTypeEnum::DATE, 'between', $between, '2026-01-01');
        $this->assertMatches(FieldTypeEnum::DATE, 'between', $between, '2026-12-31');
        $this->assertDoesNotMatch(FieldTypeEnum::DATE, 'between', $between, '2027-01-01');
    }

    // ---- TEXT ----

    public function test_text_equals_is_case_insensitive(): void
    {
        $this->assertMatches(FieldTypeEnum::TEXT, 'eq', 'São Paulo', 'são paulo');
        $this->assertDoesNotMatch(FieldTypeEnum::TEXT, 'eq', 'São Paulo', 'Rio');
    }

    public function test_text_not_equals(): void
    {
        $this->assertMatches(FieldTypeEnum::TEXT, 'neq', 'São Paulo', 'Rio');
        $this->assertDoesNotMatch(FieldTypeEnum::TEXT, 'neq', 'São Paulo', 'SÃO PAULO');
    }

    public function test_text_contains(): void
    {
        $this->assertMatches(FieldTypeEnum::TEXT, 'contains', 'urgente', 'Pedido URGENTE do cliente');
        $this->assertDoesNotMatch(FieldTypeEnum::TEXT, 'contains', 'urgente', 'Pedido normal');
    }

    // ---- SELECT / RADIO ----

    public function test_select_equals_is_exact(): void
    {
        $this->assertMatches(FieldTypeEnum::SELECT, 'eq', 'Opção A', 'Opção A');
        $this->assertDoesNotMatch(FieldTypeEnum::SELECT, 'eq', 'Opção A', 'Opção B');
    }

    public function test_select_in_list(): void
    {
        $this->assertMatches(FieldTypeEnum::RADIO, 'in', ['Opção A', 'Opção B'], 'Opção B');
        $this->assertDoesNotMatch(FieldTypeEnum::RADIO, 'in', ['Opção A', 'Opção B'], 'Opção C');
    }

    // ---- CHECKBOX ----

    public function test_checkbox_true_and_false(): void
    {
        $this->assertMatches(FieldTypeEnum::CHECKBOX, 'is_true', null, '1');
        $this->assertMatches(FieldTypeEnum::CHECKBOX, 'is_true', null, 'true');
        $this->assertDoesNotMatch(FieldTypeEnum::CHECKBOX, 'is_true', null, '0');

        $this->assertMatches(FieldTypeEnum::CHECKBOX, 'is_false', null, '0');
        // Checkbox sem valor gravado conta como "não"
        $this->assertMatches(FieldTypeEnum::CHECKBOX, 'is_false', null, null);
    }

    // ---- VAZIO / PREENCHIDO ----

    public function test_empty_and_filled(): void
    {
        $this->assertMatches(FieldTypeEnum::TEXT, 'empty', null, null);
        $this->assertMatches(FieldTypeEnum::TEXT, 'empty', null, '   ');
        $this->assertDoesNotMatch(FieldTypeEnum::TEXT, 'empty', null, 'algo');

        $this->assertMatches(FieldTypeEnum::TEXT, 'filled', null, 'algo');
        $this->assertMatches(FieldTypeEnum::NUMBER, 'filled', null, '0');
        $this->assertDoesNotMatch(FieldTypeEnum::TEXT, 'filled', null, null);
    }

    // ---- SEMÂNTICA DA TABELA ----

    public function test_first_hit_policy_uses_rule_order(): void
    {
        $field = CustomField::factory()->create(['field_type' => FieldTypeEnum::NUMBER]);
        $demand = Demand::factory()->create(['entity_id' => $this->entity->id]);
        DemandFieldValue::create(['demand_id' => $demand->id, 'custom_field_id' => $field->id, 'value' => '5000']);

        $otherStatus = ProcessStatus::factory()->create();
        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'default_to_status_id' => $this->defaultStatus->id,
        ]);

        // As duas regras casam; vence a primeira pela ordem
        TransitionRule::create([
            'status_transition_id' => $transition->id,
            'rule_order' => 1,
            'conditions' => [['field_id' => $field->id, 'operator' => 'gt', 'value' => '100']],
            'to_status_id' => $otherStatus->id,
        ]);
        TransitionRule::create([
            'status_transition_id' => $transition->id,
            'rule_order' => 0,
            'conditions' => [['field_id' => $field->id, 'operator' => 'gt', 'value' => '1000']],
            'to_status_id' => $this->matchStatus->id,
        ]);

        $decision = $this->evaluator->resolve($transition, $demand);

        $this->assertSame($this->matchStatus->id, $decision->toStatusId);
        $this->assertSame(0, $decision->matchedRule?->rule_order);
    }

    public function test_conditions_combine_with_and(): void
    {
        $fieldA = CustomField::factory()->create(['field_type' => FieldTypeEnum::NUMBER]);
        $fieldB = CustomField::factory()->create(['field_type' => FieldTypeEnum::TEXT]);
        $demand = Demand::factory()->create(['entity_id' => $this->entity->id]);
        DemandFieldValue::create(['demand_id' => $demand->id, 'custom_field_id' => $fieldA->id, 'value' => '2000']);
        DemandFieldValue::create(['demand_id' => $demand->id, 'custom_field_id' => $fieldB->id, 'value' => 'normal']);

        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'default_to_status_id' => $this->defaultStatus->id,
        ]);

        // Primeira condição casa (2000 > 1000), segunda não ("normal" != "urgente")
        TransitionRule::create([
            'status_transition_id' => $transition->id,
            'rule_order' => 0,
            'conditions' => [
                ['field_id' => $fieldA->id, 'operator' => 'gt', 'value' => '1000'],
                ['field_id' => $fieldB->id, 'operator' => 'eq', 'value' => 'urgente'],
            ],
            'to_status_id' => $this->matchStatus->id,
        ]);

        $decision = $this->evaluator->resolve($transition, $demand);

        $this->assertSame($this->defaultStatus->id, $decision->toStatusId);
        $this->assertNull($decision->matchedRule);
    }

    public function test_falls_back_to_default_when_no_rule_matches(): void
    {
        [$decision] = $this->resolveSingleCondition(FieldTypeEnum::NUMBER, 'gt', '1000', '500');

        $this->assertSame($this->defaultStatus->id, $decision->toStatusId);
        $this->assertNull($decision->matchedRule);
    }

    public function test_empty_input_field_blocks_with_field_name(): void
    {
        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessageMatches('/precisa estar preenchido/');

        $field = CustomField::factory()->create(['field_type' => FieldTypeEnum::NUMBER, 'name' => 'Valor da Compra']);
        $demand = Demand::factory()->create(['entity_id' => $this->entity->id]);

        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'default_to_status_id' => $this->defaultStatus->id,
        ]);
        TransitionRule::create([
            'status_transition_id' => $transition->id,
            'rule_order' => 0,
            'conditions' => [['field_id' => $field->id, 'operator' => 'gt', 'value' => '1000']],
            'to_status_id' => $this->matchStatus->id,
        ]);

        try {
            $this->evaluator->resolve($transition, $demand);
        } catch (DemandTransitionException $e) {
            $this->assertStringContainsString('Valor da Compra', $e->getMessage());
            throw $e;
        }
    }

    public function test_missing_default_throws(): void
    {
        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessageMatches('/senão/u');

        $demand = Demand::factory()->create(['entity_id' => $this->entity->id]);
        $transition = StatusTransition::factory()->create([
            'entity_id' => $this->entity->id,
            'default_to_status_id' => null,
        ]);

        $this->evaluator->resolve($transition, $demand);
    }
}
