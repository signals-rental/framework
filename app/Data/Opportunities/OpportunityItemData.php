<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\OpportunityItem;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API/serialisation representation of an opportunity line item.
 *
 * Money (`unit_price`, `total`) is emitted as decimal strings (RMS format) from
 * the stored integer minor units. Quantity and percentage fields are decimal
 * strings. Charge period and transaction type are exposed both as raw RMS
 * integers and as human-readable labels. Per-item date overrides are ISO-8601
 * UTC. Tree placement uses materialised `path` + derived `parent_path`/`depth`.
 *
 * External field naming (RMS-unified cut):
 *  - `item_type` — structural role (`group`/`product`/`accessory`/`service`)
 *  - `item_id` — catalogue polymorphic PK (`itemable_id`)
 *  - `itemable_type` — polymorphic class (`itemable_type`)
 *
 * @property Lazy|array<int, OpportunityItemAssetData> $assets
 */
class OpportunityItemData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $opportunity_id,
        public ?int $version_id,
        public ?int $item_id,
        public ?string $itemable_type,
        public string $item_type,
        public string $path,
        public ?string $parent_path,
        public int $depth,
        public ?int $revenue_group_id,
        public string $name,
        public ?string $description,
        public string $quantity,
        public string $dispatched_quantity,
        public string $returned_quantity,
        public string $unit_price,
        public int $charge_period,
        public string $charge_period_label,
        public string $total,
        public ?string $discount_percent,
        public ?string $tax_rate,
        public int $transaction_type,
        public string $transaction_type_label,
        public ?string $starts_at,
        public ?string $ends_at,
        public bool $is_optional,
        public object $custom_fields,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
        /** @var Lazy|array<int, OpportunityItemAssetData> */
        public Lazy|array $assets = [],
    ) {}

    public static function fromModel(OpportunityItem $item): self
    {
        return new self(
            id: $item->id,
            opportunity_id: $item->opportunity_id,
            version_id: $item->version_id,
            item_id: $item->itemable_id,
            itemable_type: $item->itemable_type,
            item_type: $item->item_type->value,
            path: (string) $item->path,
            parent_path: $item->parentPath(),
            depth: $item->depth(),
            revenue_group_id: $item->revenue_group_id,
            name: $item->name,
            description: $item->description,
            quantity: (string) $item->quantity,
            dispatched_quantity: (string) $item->dispatched_quantity,
            returned_quantity: (string) $item->returned_quantity,
            unit_price: $item->formatMoneyCost('unit_price'),
            charge_period: $item->charge_period->value,
            charge_period_label: $item->charge_period->label(),
            total: $item->formatMoneyCost('total'),
            discount_percent: $item->discount_percent !== null ? (string) $item->discount_percent : null,
            tax_rate: $item->tax_rate !== null ? (string) $item->tax_rate : null,
            transaction_type: $item->transaction_type->value,
            transaction_type_label: $item->transaction_type->label(),
            starts_at: self::formatNullableTimestamp($item->starts_at),
            ends_at: self::formatNullableTimestamp($item->ends_at),
            is_optional: $item->is_optional,
            custom_fields: (object) ($item->custom_fields ?? []),
            notes: $item->notes,
            created_at: self::formatTimestamp($item->created_at),
            updated_at: self::formatTimestamp($item->updated_at),
            assets: Lazy::whenLoaded(
                'assets',
                $item,
                fn (): array => $item->assets->map(
                    fn ($asset): OpportunityItemAssetData => OpportunityItemAssetData::fromModel($asset)
                )->all(),
            ),
        );
    }

    private static function formatNullableTimestamp(?\DateTimeInterface $timestamp): ?string
    {
        return $timestamp !== null ? self::formatTimestamp($timestamp) : null;
    }
}
