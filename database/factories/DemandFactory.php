<?php

namespace Database\Factories;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Demand>
 */
class DemandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => CustomEntity::factory(),
            'title' => fake()->sentence(4),
            'requested_by' => User::factory(),
            'created_by' => User::factory(),
            'status' => DemandStatusEnum::ACTIVE,
            'priority' => DemandPriorityEnum::MEDIUM,
        ];
    }
}
