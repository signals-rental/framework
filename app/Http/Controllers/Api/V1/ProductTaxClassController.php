<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TaxClasses\CreateProductTaxClass;
use App\Actions\TaxClasses\DeleteProductTaxClass;
use App\Actions\TaxClasses\UpdateProductTaxClass;
use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\TaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ProductTaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductTaxClassController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    /**
     * List product tax classes.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        $query = ProductTaxClass::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $classes = $paginator->getCollection()->map(
            fn (ProductTaxClass $taxClass): array => TaxClassData::fromModel($taxClass)->toArray()
        )->all();

        return $this->respondWithCollection($classes, 'product_tax_classes', $paginator);
    }

    /**
     * Show a single product tax class.
     */
    public function show(ProductTaxClass $productTaxClass): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        return $this->respondWith(
            TaxClassData::fromModel($productTaxClass)->toArray(),
            'product_tax_class',
        );
    }

    /**
     * Create a product tax class.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(CreateTaxClassData::rules());
        $dto = CreateTaxClassData::from($validated);

        $result = (new CreateProductTaxClass)($dto);

        return $this->respondWith(
            $result->toArray(),
            'product_tax_class',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a product tax class.
     */
    public function update(Request $request, ProductTaxClass $productTaxClass): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(UpdateTaxClassData::rules());
        $dto = UpdateTaxClassData::from($validated);

        $result = (new UpdateProductTaxClass)($productTaxClass, $dto);

        return $this->respondWith(
            $result->toArray(),
            'product_tax_class',
        );
    }

    /**
     * Delete a product tax class.
     */
    public function destroy(ProductTaxClass $productTaxClass): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        (new DeleteProductTaxClass)($productTaxClass);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
