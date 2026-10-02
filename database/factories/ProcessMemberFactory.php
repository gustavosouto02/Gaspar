<?php

namespace Database\Factories;

use App\Models\CustomEntity;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessMember>
 */
class ProcessMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => CustomEntity::factory(),
            'user_id' => User::factory(),
            'process_role_id' => ProcessRole::factory(),
        ];
    }
}
