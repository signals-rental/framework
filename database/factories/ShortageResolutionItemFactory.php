<?php

namespace Database\Factories;

use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortageResolutionItem>
 */
class ShortageResolutionItemFactory extends Factory
{
    protected $model = ShortageResolutionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shortage_resolution_id' => ShortageResolution::factory(),
            'opportunity_item_id' => OpportunityItem::factory(),
            'quantity_allocated' => fake()->numberBetween(1, 5),
        ];
    }
}
