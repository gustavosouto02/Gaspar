<?php

namespace Database\Factories;

use App\Models\ProcessRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessRole>
 */
class ProcessRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'created_by' => User::factory(),
        ];
    }
}
