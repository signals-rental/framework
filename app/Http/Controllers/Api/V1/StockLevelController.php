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
use App\Http\Traits\ResourceActions;
use App\Models\StockLevel;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockLevelController extends Controller
{
    use FiltersQueries, ResourceActions;

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

    protected function modelClass(): string
    {
        return StockLevel::class;
    }

    protected function responseDataClass(): string
    {
        return StockLevelData::class;
    }

    protected function createDataClass(): string
    {
        return CreateStockLevelData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateStockLevelData::class;
    }

    protected function createActionClass(): string
    {
        return CreateStockLevel::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateStockLevel::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteStockLevel::class;
    }

    protected function singularKey(): string
    {
        return 'stock_level';
    }

    protected function pluralKey(): string
    {
        return 'stock_levels';
    }

    protected function entityType(): string
    {
        return 'stock_levels';
    }

    protected function permissions(): array
    {
        return ['view' => 'stock.view', 'create' => 'stock.adjust', 'edit' => 'stock.adjust', 'delete' => 'stock.adjust'];
    }

    protected function abilities(): array
    {
        return ['read' => 'stock:read', 'write' => 'stock:write'];
    }

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
        return $this->resourceIndex($request);
    }

    /**
     * Show a single stock level.
     */
    #[ApiResponse(200, 'Stock level details', type: 'array{stock_level: array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, barcode: string|null, location: string|null, stock_type: int, stock_category: int, quantity_held: string, quantity_allocated: string, quantity_unavailable: string, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, StockLevel $stockLevel): JsonResponse
    {
        return $this->resourceShow($request, $stockLevel);
    }

    /**
     * Create a new stock level.
     */
    #[ApiResponse(201, 'Stock level created', type: 'array{stock_level: array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing stock level.
     */
    #[ApiResponse(200, 'Stock level updated', type: 'array{stock_level: array{id: int, product_id: int, store_id: int|null, item_name: string|null, asset_number: string|null, serial_number: string|null, created_at: string, updated_at: string}}')]
    public function update(Request $request, StockLevel $stockLevel): JsonResponse
    {
        return $this->resourceUpdate($request, $stockLevel);
    }

    /**
     * Delete a stock level.
     */
    #[ApiResponse(204, 'Stock level deleted')]
    public function destroy(StockLevel $stockLevel): JsonResponse
    {
        return $this->resourceDestroy($stockLevel);
    }
}
