<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rates\CreateProductRate;
use App\Actions\Rates\DeleteProductRate;
use App\Actions\Rates\UpdateProductRate;
use App\Data\Rates\CreateProductRateData;
use App\Data\Rates\ProductRateData;
use App\Data\Rates\UpdateProductRateData;
use App\Enums\RateTransactionType;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Product;
use App\Models\ProductRate;
use App\Services\RateEngine\ProductRateOverlapChecker;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductRateController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'rate_definition_id',
        'store_id',
        'transaction_type',
        'currency',
        'priority',
        'valid_from',
        'valid_to',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'transaction_type',
        'price',
        'priority',
        'valid_from',
        'valid_to',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'rateDefinition',
        'store',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'rateDefinition',
    ];

    /**
     * List the rate assignments for a product.
     *
     * Supports Ransack `q[...]` filtering, `sort`, and `?include=` on the
     * whitelisted fields and relationships.
     */
    #[ApiResponse(200, 'Product rate list', type: 'array{product_rates: list<array{id: int, product_id: int, rate_definition_id: int, store_id: int|null, transaction_type: string, transaction_type_name: string, price: string, currency: string, valid_from: string|null, valid_to: string|null, priority: int, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('rates.view', 'rates:read');

        $query = $product->rates()->getQuery();
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (ProductRate $rate): array => ProductRateData::fromModel($rate)->toArray())
            ->all();

        return response()->json([
            'product_rates' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * Show a single rate assignment for a product.
     */
    #[ApiResponse(200, 'Product rate details', type: 'array{product_rate: array{id: int, product_id: int, rate_definition_id: int, store_id: int|null, transaction_type: string, transaction_type_name: string, price: string, currency: string, valid_from: string|null, valid_to: string|null, priority: int, created_at: string, updated_at: string}}')]
    public function show(Product $product, ProductRate $rate): JsonResponse
    {
        $this->authorizeApi('rates.view', 'rates:read');

        $this->ensureBelongsToProduct($product, $rate);

        $rate->load('rateDefinition');

        return $this->respondWith(ProductRateData::fromModel($rate)->toArray(), 'product_rate');
    }

    /**
     * Assign a rate to a product.
     *
     * The response `meta.overlapping_rate_ids` lists any existing same-priority
     * rates with overlapping validity windows — a non-blocking configuration
     * warning.
     */
    #[ApiResponse(201, 'Product rate created', type: 'array{product_rate: array{id: int, product_id: int, rate_definition_id: int, store_id: int|null, transaction_type: string, transaction_type_name: string, price: string, currency: string, valid_from: string|null, valid_to: string|null, priority: int, created_at: string, updated_at: string}, meta: array{overlapping_rate_ids: list<int>}}')]
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('rates.create', 'rates:write');

        $request->merge(['product_id' => $product->id]);
        $validated = $request->validate(CreateProductRateData::rules());
        $dto = CreateProductRateData::from($validated);

        $result = (new CreateProductRate)($dto);

        return response()->json([
            'product_rate' => $result->toArray(),
            'meta' => ['overlapping_rate_ids' => $this->overlappingRateIds($result)],
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a rate assignment for a product.
     *
     * As with creation, the response `meta.overlapping_rate_ids` lists any
     * existing same-priority rates with overlapping validity windows — a
     * non-blocking configuration warning.
     */
    #[ApiResponse(200, 'Product rate updated', type: 'array{product_rate: array{id: int, product_id: int, rate_definition_id: int, store_id: int|null, transaction_type: string, transaction_type_name: string, price: string, currency: string, valid_from: string|null, valid_to: string|null, priority: int, created_at: string, updated_at: string}, meta: array{overlapping_rate_ids: list<int>}}')]
    public function update(Request $request, Product $product, ProductRate $rate): JsonResponse
    {
        $this->authorizeApi('rates.edit', 'rates:write');

        $this->ensureBelongsToProduct($product, $rate);

        $validated = $request->validate(UpdateProductRateData::rules());
        $dto = UpdateProductRateData::from($validated);

        $result = (new UpdateProductRate)($rate, $dto);

        return response()->json([
            'product_rate' => $result->toArray(),
            'meta' => ['overlapping_rate_ids' => $this->overlappingRateIds($result)],
        ]);
    }

    /**
     * Remove a rate assignment from a product.
     */
    #[ApiResponse(204, 'Product rate removed')]
    public function destroy(Product $product, ProductRate $rate): JsonResponse
    {
        $this->authorizeApi('rates.delete', 'rates:write');

        $this->ensureBelongsToProduct($product, $rate);

        (new DeleteProductRate)($rate);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Abort with 404 when the rate does not belong to the routed product.
     */
    private function ensureBelongsToProduct(Product $product, ProductRate $rate): void
    {
        if ($rate->product_id !== $product->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * IDs of existing rates that overlap the given assignment at the same
     * priority (excluding the assignment itself).
     *
     * @return list<int>
     */
    private function overlappingRateIds(ProductRateData $rate): array
    {
        return app(ProductRateOverlapChecker::class)
            ->overlapping(
                $rate->product_id,
                $rate->store_id,
                RateTransactionType::from($rate->transaction_type),
                $rate->priority,
                $rate->valid_from,
                $rate->valid_to,
                $rate->id,
            )
            ->pluck('id')
            ->all();
    }
}
