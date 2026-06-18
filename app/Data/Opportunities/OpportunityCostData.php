<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\OpportunityCost;
use Spatie\LaravelData\Data;

/**
 * API/serialisation representation of an opportunity cost.
 *
 * Money (`amount`) is emitted as a decimal string (RMS format) from the stored
 * integer minor units. Quantity and tax-rate are decimal strings. Cost type and
 * transaction type are exposed both as raw RMS integers and as human-readable
 * labels.
 */
class OpportunityCostData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $opportunity_id,
        public string $description,
        public int $cost_type,
        public string $cost_type_label,
        public int $transaction_type,
        public string $transaction_type_label,
        public string $amount,
        public string $quantity,
        public ?string $tax_rate,
        public ?string $currency_code,
        public bool $is_optional,
        public int $sort_order,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(OpportunityCost $cost): self
    {
        return new self(
            id: $cost->id,
            opportunity_id: $cost->opportunity_id,
            description: $cost->description,
            cost_type: $cost->cost_type->value,
            cost_type_label: $cost->cost_type->label(),
            transaction_type: $cost->transaction_type->value,
            transaction_type_label: $cost->transaction_type->label(),
            amount: $cost->formatMoneyCost('amount'),
            quantity: (string) $cost->quantity,
            tax_rate: $cost->tax_rate !== null ? (string) $cost->tax_rate : null,
            currency_code: $cost->currency_code,
            is_optional: $cost->is_optional,
            sort_order: $cost->sort_order,
            notes: $cost->notes,
            created_at: self::formatTimestamp($cost->created_at),
            updated_at: self::formatTimestamp($cost->updated_at),
        );
    }
}
