<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Currencies\CurrencyData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'code',
        'name',
        'is_enabled',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'code',
        'name',
    ];

    /**
     * List currencies with filtering, sorting, and pagination.
     *
     * @response array{currencies: list<CurrencyData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('static-data.view', 'currencies:read');

        $query = Currency::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $currencies = $paginator->getCollection()->map(
            fn (Currency $currency): array => CurrencyData::fromModel($currency)->toArray()
        )->all();

        return $this->respondWithCollection($currencies, 'currencies', $paginator);
    }

    /**
     * Show a single currency.
     *
     * @response array{currency: CurrencyData}
     */
    public function show(Currency $currency): JsonResponse
    {
        $this->authorizeApi('static-data.view', 'currencies:read');

        return $this->respondWith(
            CurrencyData::fromModel($currency)->toArray(),
            'currency',
        );
    }
}
