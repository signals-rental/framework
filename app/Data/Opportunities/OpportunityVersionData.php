<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\OpportunityVersion;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API/serialisation representation of a quote version (opportunity-lifecycle.md
 * §8).
 *
 * Money totals are emitted as decimal strings (RMS format) from the stored
 * integer minor units (NET, ex-tax). Type and status are exposed both as raw
 * integers and as human-readable labels. The `items` relationship is lazy — only
 * present when eager-loaded.
 */
class OpportunityVersionData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $opportunity_id,
        public int $version_number,
        public ?int $parent_version_id,
        public ?int $superseded_by_version_id,
        public int $version_type,
        public string $version_type_label,
        public ?string $label,
        public bool $is_active,
        public int $status,
        public string $status_label,
        public ?string $decline_reason,
        public string $charge_excluding_tax_total,
        public string $tax_total,
        public string $charge_including_tax_total,
        public string $charge_total,
        public ?string $notes,
        public ?int $created_by,
        public ?string $sent_at,
        public ?string $accepted_at,
        public ?string $declined_at,
        public string $created_at,
        public string $updated_at,
        /** @var Lazy|array<int, OpportunityItemData> */
        public Lazy|array $items = [],
    ) {}

    public static function fromModel(OpportunityVersion $version): self
    {
        return new self(
            id: $version->id,
            opportunity_id: $version->opportunity_id,
            version_number: $version->version_number,
            parent_version_id: $version->parent_version_id,
            superseded_by_version_id: $version->superseded_by_version_id,
            version_type: $version->version_type->value,
            version_type_label: $version->version_type->label(),
            label: $version->label,
            is_active: $version->is_active,
            status: $version->status->value,
            status_label: $version->status->label(),
            decline_reason: $version->decline_reason,
            charge_excluding_tax_total: $version->formatMoneyCost('charge_excluding_tax_total'),
            tax_total: $version->formatMoneyCost('tax_total'),
            charge_including_tax_total: $version->formatMoneyCost('charge_including_tax_total'),
            charge_total: $version->formatMoneyCost('charge_total'),
            notes: $version->notes,
            created_by: $version->created_by,
            sent_at: self::formatNullableTimestamp($version->sent_at),
            accepted_at: self::formatNullableTimestamp($version->accepted_at),
            declined_at: self::formatNullableTimestamp($version->declined_at),
            created_at: self::formatTimestamp($version->created_at),
            updated_at: self::formatTimestamp($version->updated_at),
            items: Lazy::whenLoaded(
                'items',
                $version,
                fn (): array => $version->items->map(
                    fn ($item): OpportunityItemData => OpportunityItemData::fromModel($item)
                )->all(),
            ),
        );
    }

    private static function formatNullableTimestamp(?\DateTimeInterface $timestamp): ?string
    {
        return $timestamp !== null ? self::formatTimestamp($timestamp) : null;
    }
}
