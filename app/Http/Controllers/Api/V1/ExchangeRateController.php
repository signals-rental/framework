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
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExchangeRateController extends Controller
{
    use FiltersQueries;

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

    /**
     * List exchange rates with filtering, sorting, and pagination.
     *
     * @response array{exchange_rates: list<ExchangeRateData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'exchange_rates:read');

        $query = ExchangeRate::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $rates = $paginator->getCollection()->map(
            fn (ExchangeRate $rate): array => ExchangeRateData::fromModel($rate)->toArray()
        )->all();

        return $this->respondWithCollection($rates, 'exchange_rates', $paginator);
    }

    /**
     * Show a single exchange rate.
     *
     * @response array{exchange_rate: ExchangeRateData}
     */
    public function show(ExchangeRate $exchangeRate): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'exchange_rates:read');

        return $this->respondWith(
            ExchangeRateData::fromModel($exchangeRate)->toArray(),
            'exchange_rate',
        );
    }

    /**
     * Create a new exchange rate.
     *
     * @response 201 array{exchange_rate: ExchangeRateData}
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'exchange_rates:write');

        $validated = $request->validate(CreateExchangeRateData::rules());
        $dto = CreateExchangeRateData::from($validated);

        $result = (new CreateExchangeRate)($dto);

        return $this->respondWith(
            $result->toArray(),
            'exchange_rate',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing exchange rate.
     *
     * @response array{exchange_rate: ExchangeRateData}
     */
    public function update(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'exchange_rates:write');

        $validated = $request->validate(UpdateExchangeRateData::rules());
        $dto = UpdateExchangeRateData::from($validated);

        $result = (new UpdateExchangeRate)($exchangeRate, $dto);

        return $this->respondWith(
            $result->toArray(),
            'exchange_rate',
        );
    }

    /**
     * Delete an exchange rate.
     */
    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'exchange_rates:write');

        (new DeleteExchangeRate)($exchangeRate);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
