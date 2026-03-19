<?php

namespace App\Data\ExchangeRates;

use Spatie\LaravelData\Data;

class UpdateExchangeRateData extends Data
{
    public function __construct(
        public ?string $rate = null,
        public ?string $inverse_rate = null,
        public ?string $source = null,
        public ?string $effective_at = null,
        public ?string $expires_at = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'rate' => ['sometimes', 'numeric', 'gt:0'],
            'inverse_rate' => ['nullable', 'numeric', 'gt:0'],
            'source' => ['sometimes', 'string', 'max:50'],
            'effective_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
        ];
    }
}
