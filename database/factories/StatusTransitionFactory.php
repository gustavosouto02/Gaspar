<?php

namespace Database\Factories;

use App\Models\CustomEntity;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusTransition>
 */
class StatusTransitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => CustomEntity::factory(),
            'from_status_id' => null,
            'to_status_id' => ProcessStatus::factory(),
            'label' => fake()->words(2, true),
            'display_order' => 0,
        ];
    }
}
