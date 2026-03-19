<?php

namespace App\Data\ExchangeRates;

use Spatie\LaravelData\Data;

class CreateExchangeRateData extends Data
{
    public function __construct(
        public string $source_currency_code,
        public string $target_currency_code,
        public string $rate,
        public ?string $inverse_rate = null,
        public string $source = 'manual',
        public ?string $effective_at = null,
        public ?string $expires_at = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'source_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'target_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code', 'different:source_currency_code'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'inverse_rate' => ['nullable', 'numeric', 'gt:0'],
            'source' => ['sometimes', 'string', 'max:50'],
            'effective_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
        ];
    }
}
