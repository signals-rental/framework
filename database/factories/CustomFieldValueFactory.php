<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldValue>
 */
class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_field_id' => CustomField::factory(),
            'entity_type' => 'Member',
            'entity_id' => 1,
            'value_string' => fake()->word(),
        ];
    }

    public function withStringValue(string $value): static
    {
        return $this->state(fn () => ['value_string' => $value]);
    }

    public function withBooleanValue(bool $value): static
    {
        return $this->state(fn () => [
            'value_string' => null,
            'value_boolean' => $value,
        ]);
    }

    public function withIntegerValue(int $value): static
    {
        return $this->state(fn () => [
            'value_string' => null,
            'value_integer' => $value,
        ]);
    }
}
