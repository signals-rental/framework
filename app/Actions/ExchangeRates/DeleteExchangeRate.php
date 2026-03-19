<?php

namespace App\Actions\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Gate;

class DeleteExchangeRate
{
    public function __invoke(ExchangeRate $exchangeRate): void
    {
        Gate::authorize('settings.manage');

        $exchangeRate->delete();
    }
}
