<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds baseline effective-dated exchange rates against the GBP base currency
 * so the currency engine is demonstrable on a clean install (the table would
 * otherwise be empty and CurrencyService::getRate() would have nothing to
 * resolve). Rates are indicative manual entries, not live market data.
 *
 * Depends on CurrencySeeder having run first so the currency codes exist.
 */
class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveAt = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC');

        $rates = [
            ['source' => 'GBP', 'target' => 'USD', 'rate' => '1.27000000', 'inverse' => '0.78740157'],
            ['source' => 'GBP', 'target' => 'EUR', 'rate' => '1.18000000', 'inverse' => '0.84745763'],
        ];

        foreach ($rates as $rate) {
            ExchangeRate::query()->updateOrCreate(
                [
                    'source_currency_code' => $rate['source'],
                    'target_currency_code' => $rate['target'],
                    'effective_at' => $effectiveAt,
                ],
                [
                    'rate' => $rate['rate'],
                    'inverse_rate' => $rate['inverse'],
                    'source' => 'manual',
                    'expires_at' => null,
                ],
            );
        }
    }
}
