<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Enums\RuleOperatorEnum;
use App\Exceptions\DemandTransitionException;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Avalia a tabela de decisão de uma transição condicional (hit policy FIRST):
 * vale a primeira regra que casar, na ordem de rule_order; se nenhuma casar,
 * vale a situação padrão ("senão").
 */
class TransitionDecisionEvaluator
{
    /**
     * @throws DemandTransitionException quando falta o "senão" ou um campo de entrada está vazio
     */
    public function resolve(StatusTransition $transition, Demand $demand): TransitionDecision
    {
        $values = $demand->fieldValues()->get()->keyBy('custom_field_id');

        $rules = $transition->rules()->with('toStatus')->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $values)) {
                return new TransitionDecision($rule->to_status_id, $rule);
            }
        }

        if (! $transition->default_to_status_id) {
            throw new DemandTransitionException('Transição condicional sem situação padrão ("senão") configurada.');
        }

        return new TransitionDecision($transition->default_to_status_id, null);
    }

    /**
     * Todas as condições da regra combinam com E; regra sem condições nunca casa.
     */
    private function ruleMatches(TransitionRule $rule, Collection $values): bool
    {
        $conditions = $rule->conditions ?? [];

        if (count($conditions) === 0) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->conditionMatches($condition, $values)) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(array $condition, Collection $values): bool
    {
        $field = CustomField::find($condition['field_id'] ?? null);

        if (! $field) {
            throw new DemandTransitionException('Uma regra da transição condicional referencia um campo que não existe mais. Revise a tabela de decisão.');
        }

        $operator = RuleOperatorEnum::from($condition['operator']);
        $raw      = $values->get($field->id)?->value;
        $isEmpty  = $raw === null || trim((string) $raw) === '';

        if ($operator === RuleOperatorEnum::IS_EMPTY) {
            return $isEmpty;
        }
        if ($operator === RuleOperatorEnum::IS_FILLED) {
            return ! $isEmpty;
        }

        // Checkbox vazio conta como "não"
        if ($operator === RuleOperatorEnum::IS_TRUE) {
            return $this->toBool($raw) === true;
        }
        if ($operator === RuleOperatorEnum::IS_FALSE) {
            return $this->toBool($raw) === false;
        }

        if ($isEmpty) {
            throw new DemandTransitionException("O campo \"{$field->name}\" precisa estar preenchido para avaliar a transição condicional.");
        }

        $expected = $condition['value'] ?? null;

        return match ($field->field_type) {
            FieldTypeEnum::NUMBER => $this->compareNumeric($raw, $operator, $expected),
            FieldTypeEnum::DATE => $this->compareDate($raw, $operator, $expected),
            FieldTypeEnum::SELECT, FieldTypeEnum::RADIO => $this->compareChoice($raw, $operator, $expected),
            default => $this->compareText($raw, $operator, $expected), // TEXT, TEXTAREA, EMAIL
        };
    }

    private function compareNumeric(string $raw, RuleOperatorEnum $operator, mixed $expected): bool
    {
        $value = $this->toFloat($raw);

        if ($operator === RuleOperatorEnum::BETWEEN) {
            $min = $this->toFloat((string) ($expected['min'] ?? ''));
            $max = $this->toFloat((string) ($expected['max'] ?? ''));

            return $value >= $min && $value <= $max;
        }

        $target = $this->toFloat((string) $expected);

        return match ($operator) {
            RuleOperatorEnum::EQUALS => $value == $target,
            RuleOperatorEnum::NOT_EQUALS => $value != $target,
            RuleOperatorEnum::GREATER => $value > $target,
            RuleOperatorEnum::GREATER_OR_EQUAL => $value >= $target,
            RuleOperatorEnum::LESS => $value < $target,
            RuleOperatorEnum::LESS_OR_EQUAL => $value <= $target,
            default => false,
        };
    }

    private function compareDate(string $raw, RuleOperatorEnum $operator, mixed $expected): bool
    {
        $value = Carbon::parse(trim($raw))->startOfDay();

        if ($operator === RuleOperatorEnum::BETWEEN) {
            $min = Carbon::parse((string) ($expected['min'] ?? ''))->startOfDay();
            $max = Carbon::parse((string) ($expected['max'] ?? ''))->startOfDay();

            return $value->betweenIncluded($min, $max);
        }

        $target = Carbon::parse((string) $expected)->startOfDay();

        return match ($operator) {
            RuleOperatorEnum::EQUALS => $value->isSameDay($target),
            RuleOperatorEnum::NOT_EQUALS => ! $value->isSameDay($target),
            RuleOperatorEnum::GREATER => $value->greaterThan($target),
            RuleOperatorEnum::GREATER_OR_EQUAL => $value->greaterThanOrEqualTo($target),
            RuleOperatorEnum::LESS => $value->lessThan($target),
            RuleOperatorEnum::LESS_OR_EQUAL => $value->lessThanOrEqualTo($target),
            default => false,
        };
    }

    private function compareChoice(string $raw, RuleOperatorEnum $operator, mixed $expected): bool
    {
        $value = trim($raw);

        return match ($operator) {
            RuleOperatorEnum::EQUALS => $value === trim((string) $expected),
            RuleOperatorEnum::IN_LIST => in_array($value, array_map('strval', (array) $expected), true),
            default => false,
        };
    }

    private function compareText(string $raw, RuleOperatorEnum $operator, mixed $expected): bool
    {
        $value  = mb_strtolower(trim($raw));
        $target = mb_strtolower(trim((string) $expected));

        return match ($operator) {
            RuleOperatorEnum::EQUALS => $value === $target,
            RuleOperatorEnum::NOT_EQUALS => $value !== $target,
            RuleOperatorEnum::CONTAINS => $target !== '' && str_contains($value, $target),
            default => false,
        };
    }

    /**
     * Converte texto numérico aceitando vírgula decimal ("1.000,50" e "1000.50")
     */
    private function toFloat(string $value): float
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    private function toBool(?string $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
