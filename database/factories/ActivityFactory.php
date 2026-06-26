<?php

namespace Database\Factories;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'type_id' => $this->typeValueId(ActivityType::Task),
            'status_id' => ActivityStatus::Scheduled,
            'priority' => ActivityPriority::Normal,
            'time_status' => TimeStatus::Free,
            'completed' => false,
            'owned_by' => User::factory(),
        ];
    }

    public function task(): static
    {
        return $this->state(fn (): array => [
            'type_id' => $this->typeValueId(ActivityType::Task),
        ]);
    }

    public function call(): static
    {
        return $this->state(fn (): array => [
            'type_id' => $this->typeValueId(ActivityType::Call),
        ]);
    }

    public function meeting(): static
    {
        return $this->state(fn (): array => [
            'type_id' => $this->typeValueId(ActivityType::Meeting),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'time_status' => TimeStatus::Busy,
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (): array => [
            'type_id' => $this->typeValueId(ActivityType::Email),
        ]);
    }

    public function note(): static
    {
        return $this->state(fn (): array => [
            'type_id' => $this->typeValueId(ActivityType::Note),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status_id' => ActivityStatus::Completed,
            'completed' => true,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'completed' => false,
            'status_id' => ActivityStatus::Scheduled,
        ]);
    }

    public function forMember(Member $member): static
    {
        return $this->state(fn () => [
            'regarding_type' => Member::class,
            'regarding_id' => $member->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'regarding_type' => Product::class,
            'regarding_id' => $product->id,
        ]);
    }

    public function forStockLevel(StockLevel $stockLevel): static
    {
        return $this->state(fn () => [
            'regarding_type' => StockLevel::class,
            'regarding_id' => $stockLevel->id,
        ]);
    }

    public function forOpportunity(Opportunity $opportunity): static
    {
        return $this->state(fn () => [
            'regarding_type' => Opportunity::class,
            'regarding_id' => $opportunity->id,
        ]);
    }

    /**
     * Resolve the "Activity Type" list value id for a default type, creating the
     * list + value if absent so tests that don't seed still produce valid rows.
     */
    private function typeValueId(ActivityType $type): int
    {
        $list = ListName::query()->firstOrCreate(
            ['name' => 'Activity Type'],
            ['description' => 'Activity Type options', 'is_system' => true],
        );

        $sortOrder = (int) array_search($type, ActivityType::cases(), true);

        return ListValue::query()->firstOrCreate(
            ['list_name_id' => $list->id, 'name' => $type->label()],
            [
                'sort_order' => $sortOrder,
                'is_system' => true,
                'is_active' => true,
                'metadata' => ['icon' => $type->icon()],
            ],
        )->id;
    }
}
