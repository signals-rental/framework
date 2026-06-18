<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Input DTO for setting a manual deal-total override on an opportunity.
 *
 * `deal_total` accepts an int (already minor units) or a decimal string/float
 * (major units against `currency`). `currency` is the sibling scale for the
 * {@see MoneyInput} cast.
 */
class SetDealPriceData extends Data
{
    public function __construct(
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public int $deal_total = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'deal_total' => ['required', 'numeric', 'min:0'],
        ];
    }
}
