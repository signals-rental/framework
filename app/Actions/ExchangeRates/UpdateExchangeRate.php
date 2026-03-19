<?php

namespace App\Actions\ExchangeRates;

use App\Data\ExchangeRates\ExchangeRateData;
use App\Data\ExchangeRates\UpdateExchangeRateData;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Gate;

class UpdateExchangeRate
{
    public function __invoke(ExchangeRate $exchangeRate, UpdateExchangeRateData $data): ExchangeRateData
    {
        Gate::authorize('settings.manage');

        $attributes = array_filter([
            'rate' => $data->rate,
            'source' => $data->source,
            'effective_at' => $data->effective_at,
            'expires_at' => $data->expires_at,
        ], fn (mixed $value): bool => $value !== null);

        // Auto-compute inverse if rate changed but inverse not provided
        if ($data->rate !== null && $data->inverse_rate === null) {
            if (bccomp($data->rate, '0', 8) === 0) {
                throw new \InvalidArgumentException('Exchange rate cannot be zero.');
            }
            $attributes['inverse_rate'] = bcdiv('1', $data->rate, 8);
        } elseif ($data->inverse_rate !== null) {
            $attributes['inverse_rate'] = $data->inverse_rate;
        }

        $exchangeRate->update($attributes);

        return ExchangeRateData::fromModel($exchangeRate->fresh());
    }
}
