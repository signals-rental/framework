<?php

namespace App\Data\Currencies;

use App\Models\Currency;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CurrencyData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $symbol,
        public int $decimal_places,
        public string $symbol_position,
        public string $thousand_separator,
        public string $decimal_separator,
        public bool $is_enabled,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Currency $currency): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $currency->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $currency->updated_at;

        return new self(
            id: $currency->id,
            code: $currency->code,
            name: $currency->name,
            symbol: $currency->symbol,
            decimal_places: $currency->decimal_places,
            symbol_position: $currency->symbol_position,
            thousand_separator: $currency->thousand_separator,
            decimal_separator: $currency->decimal_separator,
            is_enabled: $currency->is_enabled,
            created_at: $createdAt->utc()->toIso8601String(),
            updated_at: $updatedAt->utc()->toIso8601String(),
        );
    }
}
