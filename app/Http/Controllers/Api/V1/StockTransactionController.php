<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateStockTransaction;
use App\Data\Products\CreateStockTransactionData;
use App\Data\Products\StockTransactionData;
use App\Http\Controllers\Api\Controller;
use App\Models\StockTransaction;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StockTransactionController extends Controller
{
    /**
     * List stock transactions for a stock level.
     */
    #[ApiResponse(200, 'Stock transactions list')]
    public function index(Request $request, int $productId, int $stockLevelId): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $stockLevel = \App\Models\StockLevel::where('id', $stockLevelId)
            ->where('product_id', $productId)
            ->firstOrFail();

        $transactions = StockTransaction::query()
            ->where('stock_level_id', $stockLevel->id)
            ->orderByDesc('transaction_at')
            ->paginate(
                perPage: (int) $request->input('per_page', 20),
                page: (int) $request->input('page', 1),
            );

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

        \App\Models\StockLevel::where('id', $stockLevelId)
            ->where('product_id', $productId)
            ->firstOrFail();

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

        \App\Models\StockLevel::where('id', $stockLevelId)
            ->where('product_id', $productId)
            ->firstOrFail();

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
}
