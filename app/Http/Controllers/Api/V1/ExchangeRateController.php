<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ExchangeRates\CreateExchangeRate;
use App\Actions\ExchangeRates\DeleteExchangeRate;
use App\Actions\ExchangeRates\UpdateExchangeRate;
use App\Data\ExchangeRates\CreateExchangeRateData;
use App\Data\ExchangeRates\ExchangeRateData;
use App\Data\ExchangeRates\UpdateExchangeRateData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'source_currency_code',
        'target_currency_code',
        'source',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'effective_at',
        'source_currency_code',
        'target_currency_code',
    ];

    protected function modelClass(): string
    {
        return ExchangeRate::class;
    }

    protected function responseDataClass(): string
    {
        return ExchangeRateData::class;
    }

    protected function createDataClass(): string
    {
        return CreateExchangeRateData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateExchangeRateData::class;
    }

    protected function createActionClass(): string
    {
        return CreateExchangeRate::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateExchangeRate::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteExchangeRate::class;
    }

    protected function singularKey(): string
    {
        return 'exchange_rate';
    }

    protected function pluralKey(): string
    {
        return 'exchange_rates';
    }

    protected function entityType(): string
    {
        return 'exchange_rates';
    }

    protected function permissions(): array
    {
        return ['view' => 'settings.manage', 'create' => 'settings.manage', 'edit' => 'settings.manage', 'delete' => 'settings.manage'];
    }

    protected function abilities(): array
    {
        return ['read' => 'exchange_rates:read', 'write' => 'exchange_rates:write'];
    }

    /**
     * List exchange rates with filtering, sorting, and pagination.
     *
     * @response array{exchange_rates: list<ExchangeRateData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single exchange rate.
     *
     * @response array{exchange_rate: ExchangeRateData}
     */
    public function show(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        return $this->resourceShow($request, $exchangeRate);
    }

    /**
     * Create a new exchange rate.
     *
     * @response 201 array{exchange_rate: ExchangeRateData}
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing exchange rate.
     *
     * @response array{exchange_rate: ExchangeRateData}
     */
    public function update(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        return $this->resourceUpdate($request, $exchangeRate);
    }

    /**
     * Delete an exchange rate.
     */
    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        return $this->resourceDestroy($exchangeRate);
    }
}
