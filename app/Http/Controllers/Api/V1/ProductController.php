<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\MergeProduct;
use App\Actions\Products\UpdateProduct;
use App\Data\Products\CreateProductData;
use App\Data\Products\MergeProductData;
use App\Data\Products\ProductData;
use App\Data\Products\UpdateProductData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use FiltersQueries, ResourceActions;

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

    /** @var array<string, string> */
    protected array $filterAliases = [
        'type' => 'product_type',
        'active' => 'is_active',
    ];

    protected ?string $customFieldModule = 'Product';

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'product_type',
        'sku',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, list<string>> */
    protected array $allowedRelationFilters = [
        'productGroup' => ['name'],
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'productGroup',
        'taxClass',
        'purchaseTaxClass',
        'stockLevels',
        'rates',
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

    protected function modelClass(): string
    {
        return Product::class;
    }

    protected function responseDataClass(): string
    {
        return ProductData::class;
    }

    protected function createDataClass(): string
    {
        return CreateProductData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateProductData::class;
    }

    protected function createActionClass(): string
    {
        return CreateProduct::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateProduct::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteProduct::class;
    }

    protected function singularKey(): string
    {
        return 'product';
    }

    protected function pluralKey(): string
    {
        return 'products';
    }

    protected function entityType(): string
    {
        return 'products';
    }

    protected function permissions(): array
    {
        return ['view' => 'products.view', 'create' => 'products.create', 'edit' => 'products.edit', 'delete' => 'products.delete'];
    }

    protected function abilities(): array
    {
        return ['read' => 'products:read', 'write' => 'products:write'];
    }

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
        return $this->resourceIndex($request);
    }

    /**
     * Show a single product.
     */
    #[ApiResponse(200, 'Product details', type: 'array{product: array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, product_group_name: string|null, sku: string|null, barcode: string|null, is_active: bool, stock_method: int, allowed_stock_type: int, weight: string|null, replacement_charge: string, buffer_percent: string, post_rent_unavailability: int, accessory_only: bool, system: bool, discountable: bool, tag_list: list<string>|null, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, Product $product): JsonResponse
    {
        return $this->resourceShow($request, $product);
    }

    /**
     * Create a new product.
     */
    #[ApiResponse(201, 'Product created', type: 'array{product: array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, is_active: bool, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing product.
     */
    #[ApiResponse(200, 'Product updated', type: 'array{product: array{id: int, name: string, description: string|null, product_type: string, product_group_id: int|null, is_active: bool, created_at: string, updated_at: string}}')]
    public function update(Request $request, Product $product): JsonResponse
    {
        return $this->resourceUpdate($request, $product);
    }

    /**
     * Delete (soft-delete) a product.
     */
    #[ApiResponse(204, 'Product deleted')]
    public function destroy(Product $product): JsonResponse
    {
        return $this->resourceDestroy($product);
    }

    /**
     * Merge another product into this product.
     *
     * The path product is the primary (surviving) record; the request `secondary_id`
     * identifies the product to merge in and archive. Both products must share the
     * same `product_type`. Stock levels, accessories, attachments and custom fields
     * transfer to the primary, then the secondary is soft-deleted.
     */
    #[ApiResponse(200, 'Primary product after merge', type: 'array{product: array{id: int, name: string, product_type: string, is_active: bool, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function merge(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        // Validation lives in MergeProductData::rules(). The path product is the
        // primary; secondary_id arrives in the request body. The DTO enforces
        // existence (excluding soft-deleted) and the self-merge guard.
        $dto = MergeProductData::validateAndCreate([
            'primary_id' => $product->id,
            'secondary_id' => $request->input('secondary_id'),
        ]);

        $primary = (new MergeProduct)($dto);

        return $this->respondWith(
            $primary->toArray(),
            'product',
        );
    }
}
