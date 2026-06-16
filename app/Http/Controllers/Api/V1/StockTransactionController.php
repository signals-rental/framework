<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateStockTransaction;
use App\Actions\Products\DeleteStockTransaction;
use App\Data\Products\CreateStockTransactionData;
use App\Data\Products\StockTransactionData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StockTransactionController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'transaction_type',
        'store_id',
        'source_id',
        'source_type',
        'manual',
        'transaction_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'transaction_type',
        'transaction_at',
        'quantity',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'stockLevel',
        'store',
        'source',
    ];

    /**
     * List stock transactions for a stock level.
     *
     * Supports Ransack `q[...]` filtering and `sort` on the whitelisted fields.
     */
    #[ApiResponse(200, 'Stock transactions list')]
    public function index(Request $request, int $productId, int $stockLevelId): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $stockLevel = $this->resolveStockLevel($productId, $stockLevelId);

        $query = StockTransaction::query()->where('stock_level_id', $stockLevel->id);
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $this->hasExplicitSort($request)) {
            $query->orderByDesc('transaction_at');
        }

        $transactions = $this->paginateQuery($query, $request);

        $items = $transactions->getCollection()->map(
            fn (StockTransaction $t): array => StockTransactionData::fromModel($t)->toArray()
        )->all();

        return response()->json([
            'stock_transactions' => $items,
            'meta' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'page' => $transactions->currentPage(),
            ],
        ]);
    }

    /**
     * Show a single stock transaction.
     */
    #[ApiResponse(200, 'Stock transaction details')]
    public function show(int $productId, int $stockLevelId, StockTransaction $stockTransaction): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $this->resolveStockLevel($productId, $stockLevelId);

        abort_unless($stockTransaction->stock_level_id === (int) $stockLevelId, 404);

        return $this->respondWith(
            StockTransactionData::fromModel($stockTransaction)->toArray(),
            'stock_transaction',
        );
    }

    /**
     * Create a stock transaction.
     *
     * Allowed transaction types: buy (4), find (5), write_off (6), sell (7), make (9).
     */
    #[ApiResponse(201, 'Stock transaction created')]
    public function store(Request $request, int $productId, int $stockLevelId): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $this->resolveStockLevel($productId, $stockLevelId);

        $request->merge(['stock_level_id' => $stockLevelId]);
        $validated = $request->validate(CreateStockTransactionData::rules());
        $dto = CreateStockTransactionData::from($validated);

        $result = (new CreateStockTransaction)($dto);

        return $this->respondWith(
            $result->toArray(),
            'stock_transaction',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Delete (reverse) a stock transaction.
     *
     * Reverses the transaction's effect on held stock and removes the ledger row.
     */
    #[ApiResponse(204, 'Stock transaction deleted')]
    public function destroy(int $productId, int $stockLevelId, StockTransaction $stockTransaction): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $this->resolveStockLevel($productId, $stockLevelId);

        abort_unless($stockTransaction->stock_level_id === (int) $stockLevelId, 404);

        (new DeleteStockTransaction)($stockTransaction);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Resolve the stock level scoped to the routed product, 404ing on mismatch.
     */
    private function resolveStockLevel(int $productId, int $stockLevelId): StockLevel
    {
        return StockLevel::query()
            ->where('id', $stockLevelId)
            ->where('product_id', $productId)
            ->firstOrFail();
    }
}
