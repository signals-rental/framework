<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tax\CreateTaxRate;
use App\Actions\Tax\DeleteTaxRate;
use App\Actions\Tax\UpdateTaxRate;
use App\Data\Tax\CreateTaxRateData;
use App\Data\Tax\TaxRateData;
use App\Data\Tax\UpdateTaxRateData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxRateController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_active',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'rate',
        'created_at',
    ];

    /**
     * List tax rates.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        $query = TaxRate::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $rates = $paginator->getCollection()->map(
            fn (TaxRate $taxRate): array => TaxRateData::fromModel($taxRate)->toArray()
        )->all();

        return $this->respondWithCollection($rates, 'tax_rates', $paginator);
    }

    /**
     * Show a single tax rate.
     */
    public function show(TaxRate $taxRate): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        return $this->respondWith(
            TaxRateData::fromModel($taxRate)->toArray(),
            'tax_rate',
        );
    }

    /**
     * Create a tax rate.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(CreateTaxRateData::rules());
        $dto = CreateTaxRateData::from($validated);

        $result = (new CreateTaxRate)($dto);

        return $this->respondWith(
            $result->toArray(),
            'tax_rate',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a tax rate.
     */
    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(UpdateTaxRateData::rules());
        $dto = UpdateTaxRateData::from($validated);

        $result = (new UpdateTaxRate)($taxRate, $dto);

        return $this->respondWith(
            $result->toArray(),
            'tax_rate',
        );
    }

    /**
     * Delete a tax rate.
     */
    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        (new DeleteTaxRate)($taxRate);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
