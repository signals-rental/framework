<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\UpdateProduct;
use App\Data\Products\CreateProductData;
use App\Data\Products\ProductData;
use App\Data\Products\UpdateProductData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Product;
use App\Services\ViewResolver;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'product_type',
        'is_active',
        'product_group_id',
        'sku',
        'barcode',
        'tax_class_id',
        'allowed_stock_type',
        'stock_method',
        'accessory_only',
        'discountable',
        'created_at',
        'updated_at',
    ];

    protected string $customFieldModule = 'Product';

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'product_type',
        'sku',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'productGroup',
        'taxClass',
        'purchaseTaxClass',
        'stockLevels',
        'accessories',
        'accessories.accessoryProduct',
        'rentalRevenueGroup',
        'saleRevenueGroup',
        'subRentalCostGroup',
        'purchaseCostGroup',
        'countryOfOrigin',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'customFieldValues',
        'productGroup',
        'taxClass',
    ];

    /**
     * List products with filtering, sorting, and pagination.
     *
     * Supports `view_id` query parameter to apply a saved custom view.
     * View filters merge with explicit `q` params (explicit params take priority).
     * View sort applies only when no explicit `sort` param is given.
     */
    #[ApiResponse(200, 'Paginated product list', type: 'array{products: list<array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, product_group_name: string|null, sku: string|null, barcode: string|null, is_active: bool, stock_method: int, allowed_stock_type: int, weight: string|null, replacement_charge: string, buffer_percent: string, post_rent_unavailability: int, accessory_only: bool, system: bool, discountable: bool, tag_list: list<string>|null, custom_fields: array<string, mixed>, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('products.view', 'products:read');

        $query = Product::query();
        $query = $this->applyIncludes($query, $request);

        // Resolve view if requested
        $viewId = $request->filled('view_id') ? (int) $request->input('view_id') : null;
        $viewResolver = app(ViewResolver::class);
        $view = $viewResolver->resolve('products', $viewId, $request->user());

        if ($view !== null) {
            // Apply view filters, merging with explicit request filters
            $explicitFilters = $request->input('q', []);
            if (! is_array($explicitFilters)) {
                $explicitFilters = [];
            }
            $query = $viewResolver->applyFilters($query, $view, $explicitFilters);

            // Apply view sort only if no explicit sort given
            if (! $request->filled('sort')) {
                $query = $viewResolver->applySort($query, $view);
            } else {
                $query = $this->applySort($query, $request);
            }
        } else {
            $query = $this->applyFilters($query, $request);
            $query = $this->applySort($query, $request);
        }

        /** @var LengthAwarePaginator<int, Product> $paginator */
        $paginator = $this->paginateQuery($query, $request);

        $products = $paginator->getCollection()->map(
            fn (Product $product): array => ProductData::fromModel($product)->toArray()
        )->all();

        $meta = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'page' => $paginator->currentPage(),
        ];

        if ($view !== null) {
            $meta['view'] = [
                'id' => $view->id,
                'name' => $view->name,
            ];
        }

        return response()->json([
            'products' => $products,
            'meta' => $meta,
        ]);
    }

    /**
     * Show a single product.
     */
    #[ApiResponse(200, 'Product details', type: 'array{product: array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, product_group_name: string|null, sku: string|null, barcode: string|null, is_active: bool, stock_method: int, allowed_stock_type: int, weight: string|null, replacement_charge: string, buffer_percent: string, post_rent_unavailability: int, accessory_only: bool, system: bool, discountable: bool, tag_list: list<string>|null, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('products.view', 'products:read');

        $this->applyIncludes(Product::query(), $request, $product);

        return $this->respondWith(
            ProductData::fromModel($product)->toArray(),
            'product',
        );
    }

    /**
     * Create a new product.
     */
    #[ApiResponse(201, 'Product created', type: 'array{product: array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, is_active: bool, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('products.create', 'products:write');

        $validated = $request->validate(CreateProductData::rules());
        $dto = CreateProductData::from($validated);

        $result = (new CreateProduct)($dto);

        return $this->respondWith(
            $result->toArray(),
            'product',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing product.
     */
    #[ApiResponse(200, 'Product updated', type: 'array{product: array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, is_active: bool, created_at: string, updated_at: string}}')]
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        $validated = $request->validate(UpdateProductData::rules());
        $dto = UpdateProductData::from($validated);

        $result = (new UpdateProduct)($product, $dto);

        return $this->respondWith(
            $result->toArray(),
            'product',
        );
    }

    /**
     * Delete (soft-delete) a product.
     */
    #[ApiResponse(204, 'Product deleted')]
    public function destroy(Product $product): JsonResponse
    {
        $this->authorizeApi('products.delete', 'products:write');

        (new DeleteProduct)($product);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
