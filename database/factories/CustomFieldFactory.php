<?php

namespace Database\Factories;

use App\Enums\FieldTypeEnum;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'key' => Str::slug($name, '_'),
            'field_type' => FieldTypeEnum::TEXT,
            'is_required' => false,
            'created_by' => User::factory(),
        ];
    }
}
