<?php

namespace Database\Factories;

use App\Models\CustomEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomEntity>
 */
class CustomEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sla_hours' => 24,
            'created_by' => User::factory(),
        ];
    }
}
