<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateStockLevel;
use App\Actions\Products\DeleteStockLevel;
use App\Actions\Products\UpdateStockLevel;
use App\Data\Products\CreateStockLevelData;
use App\Data\Products\StockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\StockLevel;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class StockLevelController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'product_id',
        'store_id',
        'stock_type',
        'stock_category',
        'serial_number',
        'barcode',
        'asset_number',
        'created_at',
    ];

    protected string $customFieldModule = 'StockLevel';

    /** @var list<string> */
    protected array $allowedSorts = [
        'product_id',
        'store_id',
        'quantity_held',
        'stock_type',
        'created_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'product',
        'store',
        'member',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'product',
        'store',
        'customFieldValues',
    ];

    /**
     * List stock levels with filtering, sorting, and pagination.
     *
     * Supports `view_id` query parameter to apply a saved custom view.
     * View filters merge with explicit `q` params (explicit params take priority).
     * View sort applies only when no explicit `sort` param is given.
     */
    #[ApiResponse(200, 'Paginated stock level list', type: 'array{stock_levels: list<array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, barcode: string|null, location: string|null, stock_type: int, stock_category: int, quantity_held: string, quantity_allocated: string, quantity_unavailable: string, custom_fields: array<string, mixed>, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $query = StockLevel::query();
        $query = $this->applyIncludes($query, $request);

        ['query' => $query, 'view' => $view] = $this->applyViewOrFilters($query, $request, 'stock_levels');

        /** @var LengthAwarePaginator<int, StockLevel> $paginator */
        $paginator = $this->paginateQuery($query, $request);

        $stockLevels = $paginator->getCollection()->map(
            fn (StockLevel $stockLevel): array => StockLevelData::fromModel($stockLevel)->toArray()
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
            'stock_levels' => $stockLevels,
            'meta' => $meta,
        ]);
    }

    /**
     * Show a single stock level.
     */
    #[ApiResponse(200, 'Stock level details', type: 'array{stock_level: array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, barcode: string|null, location: string|null, stock_type: int, stock_category: int, quantity_held: string, quantity_allocated: string, quantity_unavailable: string, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, StockLevel $stockLevel): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $this->applyIncludes(StockLevel::query(), $request, $stockLevel);

        return $this->respondWith(
            StockLevelData::fromModel($stockLevel)->toArray(),
            'stock_level',
        );
    }

    /**
     * Create a new stock level.
     */
    #[ApiResponse(201, 'Stock level created', type: 'array{stock_level: array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $validated = $request->validate(CreateStockLevelData::rules());
        $dto = CreateStockLevelData::from($validated);

        $result = (new CreateStockLevel)($dto);

        return $this->respondWith(
            $result->toArray(),
            'stock_level',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing stock level.
     */
    #[ApiResponse(200, 'Stock level updated', type: 'array{stock_level: array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, created_at: string, updated_at: string}}')]
    public function update(Request $request, StockLevel $stockLevel): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $validated = $request->validate(UpdateStockLevelData::rules());
        $dto = UpdateStockLevelData::from($validated);

        $result = (new UpdateStockLevel)($stockLevel, $dto);

        return $this->respondWith(
            $result->toArray(),
            'stock_level',
        );
    }

    /**
     * Delete a stock level.
     */
    #[ApiResponse(204, 'Stock level deleted')]
    public function destroy(StockLevel $stockLevel): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        (new DeleteStockLevel)($stockLevel);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
