<?php

namespace Database\Factories;

use App\Models\ProcessStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessStatus>
 */
class ProcessStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => 'gray',
            'is_system' => false,
            'created_by' => User::factory(),
        ];
    }
}
