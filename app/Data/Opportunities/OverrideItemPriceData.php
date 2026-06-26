<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Services\Opportunities\OpportunityItemChargeBounds;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Input DTO for overriding (or clearing) a line item's manual unit price.
 *
 * A null `unit_price` clears the override and reverts the line to rate-engine
 * pricing. `currency` is the sibling scale for the {@see MoneyInput} cast.
 */
class OverrideItemPriceData extends Data
{
    public function __construct(
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public ?int $unit_price = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:'.OpportunityItemChargeBounds::MAX_MINOR],
        ];
    }
}
