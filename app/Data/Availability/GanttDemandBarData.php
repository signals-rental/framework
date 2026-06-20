<?php

namespace App\Data\Availability;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\Demand;
use Spatie\LaravelData\Data;

/**
 * One demand bar on the availability Gantt, decomposed into its three visual
 * zones (availability-engine.md §"Gantt Chart View"):
 *
 *  - prep / buffer-before: `period_start` → `buffer_before_end` (= `starts_at`)
 *  - on-hire / active:      `starts_at` → `ends_at`
 *  - turnaround / buffer-after: `buffer_after_start` (= `ends_at`) → `period_end`
 *
 * The period boundaries come from the demand's buffered window; the zone seams
 * are the un-buffered `starts_at` / `ends_at`. `colour` is the registered demand
 * source colour (null when the source is unregistered) and `source_name` is a
 * human label for the bar's tooltip / click target.
 */
class GanttDemandBarData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $demand_id,
        public ?int $asset_id,
        public ?string $asset_serial,
        public int $quantity,
        public string $source_type,
        public int $source_id,
        public ?string $source_name,
        public ?string $colour,
        public string $phase,
        public string $period_start,
        public string $buffer_before_end,
        public string $buffer_after_start,
        public string $period_end,
        public string $starts_at,
        public string $ends_at,
    ) {}

    public static function fromDemand(Demand $demand, ?string $colour, ?string $sourceName): self
    {
        // The asset relation is eager-loaded by the Gantt query; prefer the serial
        // number, falling back to the asset number for display. Bulk demands carry
        // no asset, so guard the whole read on the asset's presence.
        $asset = $demand->asset;
        $assetSerial = $asset === null ? null : ($asset->serial_number ?? $asset->asset_number);

        return new self(
            demand_id: $demand->id,
            asset_id: $demand->asset_id,
            asset_serial: $assetSerial,
            quantity: $demand->quantity,
            source_type: $demand->source_type,
            source_id: $demand->source_id,
            source_name: $sourceName,
            colour: $colour,
            phase: $demand->phase->value,
            // The buffered window bounds the bar; the un-buffered dates are the
            // zone seams (buffer_before_end == starts_at, buffer_after_start == ends_at).
            period_start: self::formatTimestamp($demand->bufferedStartsAt()),
            buffer_before_end: self::formatTimestamp($demand->starts_at),
            buffer_after_start: self::formatTimestamp($demand->ends_at),
            period_end: self::formatTimestamp($demand->bufferedEndsAt()),
            starts_at: self::formatTimestamp($demand->starts_at),
            ends_at: self::formatTimestamp($demand->ends_at),
        );
    }
}
