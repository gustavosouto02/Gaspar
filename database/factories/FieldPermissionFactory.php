<?php

namespace Database\Factories;

use App\Enums\FieldPermissionEnum;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\FieldPermission;
use App\Models\ProcessStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldPermission>
 */
class FieldPermissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => CustomEntity::factory(),
            'custom_field_id' => CustomField::factory(),
            'process_status_id' => ProcessStatus::factory(),
            'process_role_id' => null,
            'permission' => FieldPermissionEnum::OPTIONAL,
        ];
    }
}
