<?php

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\ListName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'display_name' => str_replace('-', ' ', ucfirst($name)),
            'module_type' => 'Member',
            'field_type' => CustomFieldType::Text,
            'sort_order' => 0,
            'is_required' => false,
            'is_searchable' => true,
            'is_active' => true,
        ];
    }

    public function string(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Text]);
    }

    public function boolean(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Boolean]);
    }

    public function integer(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Integer]);
    }

    public function decimal(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Decimal]);
    }

    public function date(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Date]);
    }

    public function select(): static
    {
        return $this->state(fn () => [
            'field_type' => CustomFieldType::Select,
            'list_name_id' => ListName::factory(),
        ]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function inGroup(?CustomFieldGroup $group = null): static
    {
        return $this->state(fn () => [
            'custom_field_group_id' => $group !== null ? $group->id : CustomFieldGroup::factory(),
        ]);
    }

    public function forModule(string $moduleType): static
    {
        return $this->state(fn () => ['module_type' => $moduleType]);
    }
}
