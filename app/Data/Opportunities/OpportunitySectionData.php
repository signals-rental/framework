<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\OpportunitySection;
use Spatie\LaravelData\Data;

/**
 * API/serialisation representation of a custom line-item grouping (section).
 *
 * Sections are plain, non-event-sourced rows used by the line-item editor to
 * group lines under operator-named headings (M8-3 grouping decision).
 */
class OpportunitySectionData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $opportunity_id,
        public string $name,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(OpportunitySection $section): self
    {
        return new self(
            id: $section->id,
            opportunity_id: $section->opportunity_id,
            name: $section->name,
            sort_order: $section->sort_order,
            created_at: self::formatTimestamp($section->created_at),
            updated_at: self::formatTimestamp($section->updated_at),
        );
    }
}
