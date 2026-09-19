<?php

namespace Database\Factories;

use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransitionRule>
 */
class TransitionRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status_transition_id' => StatusTransition::factory(),
            'rule_order' => 0,
            'conditions' => [],
            'to_status_id' => ProcessStatus::factory(),
        ];
    }
}
