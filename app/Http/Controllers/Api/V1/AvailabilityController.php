<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\Product;
use App\Services\AvailabilityService;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only availability queries.
 *
 * Two-tier reads (see {@see AvailabilityService}): a single `date` yields a
 * point query computed live from demands; a `from`/`to` pair yields a range
 * query read from pre-calculated snapshots (with a `calculated_at` freshness
 * marker). Requires the `availability.view` permission and, for token auth, the
 * `availability:read` ability.
 */
class AvailabilityController extends Controller
{
    /**
     * Query availability for a product at a store.
     *
     * Provide either `date` for a point query (`{"availability": {...}}`) or
     * `from` + `to` for a range query (`{"availability": {... slots ...}}`).
     */
    #[ApiResponse(200, 'Point availability', type: 'array{availability: array{product_id: int, store_id: int, date: string, total_stock: int, total_demanded: int, available: int, demand_breakdown: array<string, int>}}')]
    #[ApiResponse(200, 'Range availability', type: 'array{availability: array{product_id: int, store_id: int, from: string, to: string, min_available: int|null, max_available: int|null, calculated_at: string|null, slots: list<array{slot_start: string, total_stock: int, total_demanded: int, available: int, demand_breakdown: array<string, int>}>}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAvailability($request);

        $validated = $this->validateQuery($request, requireProduct: true);

        return $this->respond(
            (int) $validated['product_id'],
            (int) $validated['store_id'],
            $validated['date'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );
    }

    /**
     * Query availability for a specific product (store + date/range in query).
     */
    #[ApiResponse(200, 'Point availability', type: 'array{availability: array{product_id: int, store_id: int, date: string, total_stock: int, total_demanded: int, available: int, demand_breakdown: array<string, int>}}')]
    #[ApiResponse(200, 'Range availability', type: 'array{availability: array{product_id: int, store_id: int, from: string, to: string, min_available: int|null, max_available: int|null, calculated_at: string|null, slots: list<array{slot_start: string, total_stock: int, total_demanded: int, available: int, demand_breakdown: array<string, int>}>}}')]
    public function showForProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAvailability($request);

        $validated = $this->validateQuery($request, requireProduct: false);

        return $this->respond(
            $product->id,
            (int) $validated['store_id'],
            $validated['date'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );
    }

    /**
     * List the serialised assets (stock levels) of a product at a store that are
     * free for the entire `[from, to)` window — no active demand overlaps them.
     *
     * Bulk products have no discrete assets and return an empty collection; use
     * the point/range availability endpoints for quantity-based reads.
     */
    #[ApiResponse(200, 'Available serialised assets', type: 'array{available_assets: list<array{id: int, item_name: string|null, asset_number: string|null, serial_number: string|null, barcode: string|null, location: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function availableAssets(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAvailability($request);

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = app(AvailabilityService::class)->paginateAvailableAssets(
            $product->id,
            (int) $validated['store_id'],
            Carbon::parse((string) $validated['from']),
            Carbon::parse((string) $validated['to']),
            perPage: (int) ($validated['per_page'] ?? 50),
            page: (int) ($validated['page'] ?? 1),
        );

        $items = $paginator->getCollection()->map(static fn ($asset): array => [
            'id' => $asset->id,
            'item_name' => $asset->item_name,
            'asset_number' => $asset->asset_number,
            'serial_number' => $asset->serial_number,
            'barcode' => $asset->barcode,
            'location' => $asset->location,
        ])->all();

        return $this->respondWithCollection($items, 'available_assets', $paginator);
    }

    /**
     * Multi-product availability calendar grid for a store over a date range,
     * read from the pre-calculated daily-summary read model. Optionally narrowed
     * to specific products via `product_ids[]`.
     */
    #[ApiResponse(200, 'Calendar grid', type: 'array{calendar: array{store_id: int, from: string, to: string, products: list<array{product_id: int, product_name: string|null, days: list<array{date: string, available: int, has_shortage: bool, pending_checkin: int}>}>}}')]
    public function calendar(Request $request): JsonResponse
    {
        $this->authorizeAvailability($request);

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        /** @var list<int> $productIds */
        $productIds = array_map('intval', $validated['product_ids'] ?? []);

        $from = Carbon::parse((string) $validated['from']);
        $to = Carbon::parse((string) $validated['to']);

        $products = app(AvailabilityService::class)
            ->getCalendar((int) $validated['store_id'], $from, $to, $productIds)
            ->map(static fn ($product): array => $product->toArray())
            ->values()
            ->all();

        return $this->respondWith([
            'store_id' => (int) $validated['store_id'],
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'products' => $products,
        ], 'calendar');
    }

    /**
     * Gantt demand bars for a single product at a store over a date range, read
     * directly from the `demands` table. Each demand is decomposed into its prep /
     * on-hire / turnaround zones; shortage windows are surfaced separately.
     * Optionally narrowed to specific serialised assets via `asset_ids[]`.
     */
    #[ApiResponse(200, 'Gantt model', type: 'array{gantt: array{product_id: int, store_id: int, from: string, to: string, total_stock: int, demands: list<array{demand_id: int, asset_id: int|null, asset_serial: string|null, quantity: int, source_type: string, source_id: int, source_name: string|null, colour: string|null, phase: string, period_start: string, buffer_before_end: string, buffer_after_start: string, period_end: string, starts_at: string, ends_at: string}>, shortages: list<array{from: string, to: string, severity: int, in_buffer_zone: bool}>}}')]
    public function gantt(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAvailability($request);

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['integer'],
        ]);

        /** @var list<int> $assetIds */
        $assetIds = array_map('intval', $validated['asset_ids'] ?? []);

        $data = app(AvailabilityService::class)->getGantt(
            $product->id,
            (int) $validated['store_id'],
            Carbon::parse((string) $validated['from']),
            Carbon::parse((string) $validated['to']),
            $assetIds,
        );

        return $this->respondWith($data->toArray(), 'gantt');
    }

    /**
     * Store-wide shortage sweep over a date range, read from the daily-summary
     * read model. `store_id` is optional — omit it to sweep every default-query
     * store. Drives the calendar shortage panel/widget.
     */
    #[ApiResponse(200, 'Shortages', type: 'array{shortages: list<array{product_id: int, product_name: string|null, store_id: int, date: string, available: int, severity: int, calculated_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function shortages(Request $request): JsonResponse
    {
        $this->authorizeAvailability($request);

        $validated = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $shortages = app(AvailabilityService::class)
            ->getShortages(
                (int) ($validated['store_id'] ?? 0),
                Carbon::parse((string) $validated['from']),
                Carbon::parse((string) $validated['to']),
            )
            ->map(static fn ($shortage): array => $shortage->toArray())
            ->values()
            ->all();

        return $this->respondWithCollection($shortages, 'shortages');
    }

    /**
     * Resolve the appropriate point/range response.
     */
    private function respond(int $productId, int $storeId, ?string $date, ?string $from, ?string $to): JsonResponse
    {
        $service = app(AvailabilityService::class);

        if ($date !== null) {
            $data = $service->getAvailability($productId, $storeId, Carbon::parse($date));

            return $this->respondWith($data->toArray(), 'availability');
        }

        $data = $service->getAvailabilityRange($productId, $storeId, Carbon::parse((string) $from), Carbon::parse((string) $to));

        return $this->respondWith($data->toArray(), 'availability');
    }

    /**
     * Validate the query parameters: a `store_id`, and exactly one of `date`
     * (point) or `from`+`to` (range). `product_id` is required on the flat
     * endpoint and supplied by the route binding on the nested endpoint.
     *
     * @return array<string, mixed>
     */
    private function validateQuery(Request $request, bool $requireProduct): array
    {
        $rules = [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'date' => ['nullable', 'date', 'required_without_all:from,to'],
            'from' => ['nullable', 'date', 'required_with:to', 'prohibits:date'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_with:from', 'prohibits:date'],
        ];

        if ($requireProduct) {
            $rules['product_id'] = ['required', 'integer', 'exists:products,id'];
        }

        return $request->validate($rules);
    }

    /**
     * Authorize an availability read: the user needs `availability.view`, and a
     * token (if used) must carry the `availability:read` ability.
     */
    private function authorizeAvailability(Request $request): void
    {
        Gate::authorize('availability.view');

        /** @var PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can('availability:read')) {
            abort(Response::HTTP_FORBIDDEN, 'Token does not have the required ability: availability:read');
        }
    }
}
