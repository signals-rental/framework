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
            'field_type' => CustomFieldType::String,
            'sort_order' => 0,
            'is_required' => false,
            'is_searchable' => true,
            'is_active' => true,
        ];
    }

    public function string(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::String]);
    }

    public function text(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Text]);
    }

    public function boolean(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Boolean]);
    }

    public function number(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Number]);
    }

    public function date(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Date]);
    }

    public function listOfValues(): static
    {
        return $this->state(fn () => [
            'field_type' => CustomFieldType::ListOfValues,
            'list_name_id' => ListName::factory(),
        ]);
    }

    public function multiListOfValues(): static
    {
        return $this->state(fn () => [
            'field_type' => CustomFieldType::MultiListOfValues,
            'list_name_id' => ListName::factory(),
        ]);
    }

    public function currency(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Currency]);
    }

    public function email(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Email]);
    }

    public function website(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Website]);
    }

    public function telephone(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Telephone]);
    }

    public function richText(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::RichText]);
    }

    public function colour(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Colour]);
    }

    public function percentage(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::Percentage]);
    }

    public function autoNumber(): static
    {
        return $this->state(fn () => ['field_type' => CustomFieldType::AutoNumber]);
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
