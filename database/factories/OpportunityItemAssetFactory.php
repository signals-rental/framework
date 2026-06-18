<?php

namespace Database\Factories;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItemAsset;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityItemAsset>
 */
class OpportunityItemAssetFactory extends Factory
{
    protected $model = OpportunityItemAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The PK is application-assigned ($incrementing = false), so allocate
            // one from the same ascending sequence the M5 asset event will use.
            'id' => app(SequenceAllocator::class)->next('opportunity_item_assets'),
            // Factory rows bypass the event stream, so synthesise a unique
            // snowflake-shaped state id to satisfy the unique link column.
            'state_id' => snowflake_id(),
            // Parent line item — caller may override via for()/state().
            'opportunity_item_id' => OpportunityItemFactory::new(),
            'stock_level_id' => StockLevelFactory::new()->serialised(),
            'status' => AssetAssignmentStatus::Allocated->value,
            'allocated_at' => now(),
        ];
    }

    public function dispatched(): static
    {
        return $this->state(fn (): array => [
            'status' => AssetAssignmentStatus::Dispatched->value,
            'prepared_at' => now()->subDay(),
            'dispatched_at' => now(),
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (): array => [
            'status' => AssetAssignmentStatus::CheckedIn->value,
            'dispatched_at' => now()->subDays(3),
            'returned_at' => now(),
            'checked_at' => now(),
        ]);
    }
}
