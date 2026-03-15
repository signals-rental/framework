<?php

namespace Database\Factories;

use App\Models\CustomFieldMultiValue;
use App\Models\CustomFieldValue;
use App\Models\ListValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldMultiValue>
 */
class CustomFieldMultiValueFactory extends Factory
{
    protected $model = CustomFieldMultiValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_field_value_id' => CustomFieldValue::factory(),
            'list_value_id' => ListValue::factory(),
        ];
    }
}
