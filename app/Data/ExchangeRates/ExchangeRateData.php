<?php

namespace App\Data\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ExchangeRateData extends Data
{
    public function __construct(
        public int $id,
        public string $source_currency_code,
        public string $target_currency_code,
        public string $rate,
        public string $inverse_rate,
        public string $source,
        public string $effective_at,
        public ?string $expires_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ExchangeRate $exchangeRate): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $exchangeRate->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $exchangeRate->updated_at;

        /** @var Carbon $effectiveAt */
        $effectiveAt = $exchangeRate->effective_at;

        /** @var Carbon|null $expiresAt */
        $expiresAt = $exchangeRate->expires_at;

        return new self(
            id: $exchangeRate->id,
            source_currency_code: $exchangeRate->source_currency_code,
            target_currency_code: $exchangeRate->target_currency_code,
            rate: (string) $exchangeRate->rate,
            inverse_rate: (string) $exchangeRate->inverse_rate,
            source: $exchangeRate->source,
            effective_at: $effectiveAt->utc()->toIso8601String(),
            expires_at: $expiresAt?->utc()->toIso8601String(),
            created_at: $createdAt->utc()->toIso8601String(),
            updated_at: $updatedAt->utc()->toIso8601String(),
        );
    }
}
