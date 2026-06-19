<?php

namespace Database\Factories;

use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Models\OpportunityVersion;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityVersion>
 */
class OpportunityVersionFactory extends Factory
{
    protected $model = OpportunityVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The PK is application-assigned ($incrementing = false), so allocate
            // one from the same ascending sequence the version event will use.
            'id' => app(SequenceAllocator::class)->next('opportunity_versions'),
            // Factory rows bypass the event stream, so synthesise a unique
            // snowflake-shaped state id to satisfy the unique link column.
            'state_id' => snowflake_id(),
            'opportunity_id' => OpportunityFactory::new()->quotation(),
            'version_number' => 1,
            'parent_version_id' => null,
            'version_type' => VersionType::Revision->value,
            'label' => null,
            'is_active' => true,
            'status' => VersionStatus::Draft->value,
            'charge_excluding_tax_total' => 0,
            'tax_total' => 0,
            'charge_including_tax_total' => 0,
            'charge_total' => 0,
        ];
    }

    public function alternative(): static
    {
        return $this->state(fn (): array => [
            'version_type' => VersionType::Alternative->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => VersionStatus::Sent->value,
            'sent_at' => now(),
        ]);
    }
}
