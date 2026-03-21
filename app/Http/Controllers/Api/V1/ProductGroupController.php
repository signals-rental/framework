<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateProductGroup;
use App\Actions\Products\DeleteProductGroup;
use App\Actions\Products\UpdateProductGroup;
use App\Data\Products\CreateProductGroupData;
use App\Data\Products\ProductGroupData;
use App\Data\Products\UpdateProductGroupData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ProductGroup;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ProductGroupController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'parent_id',
    ];

    protected string $customFieldModule = 'ProductGroup';

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'parent',
        'children',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'customFieldValues',
    ];

    /**
     * List product groups with filtering, sorting, and pagination.
     *
     * Supports `view_id` query parameter to apply a saved custom view.
     * View filters merge with explicit `q` params (explicit params take priority).
     * View sort applies only when no explicit `sort` param is given.
     */
    #[ApiResponse(200, 'Paginated product group list', type: 'array{product_groups: list<array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, custom_fields: array<string, mixed>, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('products.view', 'products:read');

        $query = ProductGroup::query();
        $query = $this->applyIncludes($query, $request);

        ['query' => $query, 'view' => $view] = $this->applyViewOrFilters($query, $request, 'product_groups');

        /** @var LengthAwarePaginator<int, ProductGroup> $paginator */
        $paginator = $this->paginateQuery($query, $request);

        $productGroups = $paginator->getCollection()->map(
            fn (ProductGroup $group): array => ProductGroupData::fromModel($group)->toArray()
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
            'product_groups' => $productGroups,
            'meta' => $meta,
        ]);
    }

    /**
     * Show a single product group.
     */
    #[ApiResponse(200, 'Product group details', type: 'array{product_group: array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, ProductGroup $productGroup): JsonResponse
    {
        $this->authorizeApi('products.view', 'products:read');

        $this->applyIncludes(ProductGroup::query(), $request, $productGroup);

        return $this->respondWith(
            ProductGroupData::fromModel($productGroup)->toArray(),
            'product_group',
        );
    }

    /**
     * Create a new product group.
     */
    #[ApiResponse(201, 'Product group created', type: 'array{product_group: array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('products.create', 'products:write');

        $validated = $request->validate(CreateProductGroupData::rules());
        $dto = CreateProductGroupData::from($validated);

        $result = (new CreateProductGroup)($dto);

        return $this->respondWith(
            $result->toArray(),
            'product_group',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing product group.
     */
    #[ApiResponse(200, 'Product group updated', type: 'array{product_group: array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, created_at: string, updated_at: string}}')]
    public function update(Request $request, ProductGroup $productGroup): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        $validated = $request->validate(UpdateProductGroupData::rules());
        $dto = UpdateProductGroupData::from($validated);

        $result = (new UpdateProductGroup)($productGroup, $dto);

        return $this->respondWith(
            $result->toArray(),
            'product_group',
        );
    }

    /**
     * Delete a product group.
     */
    #[ApiResponse(204, 'Product group deleted')]
    public function destroy(ProductGroup $productGroup): JsonResponse
    {
        $this->authorizeApi('products.delete', 'products:write');

        (new DeleteProductGroup)($productGroup);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
