<?php

namespace App\Services\Opportunities;

use App\Data\Opportunities\ProductSearchAccessoryData;
use App\Data\Opportunities\ProductSearchResultData;
use App\Enums\RateTransactionType;
use App\Models\Accessory;
use App\Models\Product;
use App\Models\ProductRate;
use App\Services\Api\RansackFilter;
use App\Services\AvailabilityService;
use App\Services\RateEngine\RateCalculator;
use App\Services\RateEngine\RateResolver;
use App\ValueObjects\CalculationContext;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Product search for the opportunity line-item editor's two-tier picker.
 *
 * The editor combines an instant client-side MiniSearch index (built from
 * {@see catalogueIndex()}) with a debounced SERVER fallback that calls
 * {@see search()} from a `#[Renderless]` Livewire method (M8-3b-ii). The server
 * tier exists to reach catalogue rows newer than the client's cached index and
 * to apply fuzzy/typo-tolerant ranking.
 *
 * RANKING is driver-aware:
 *  - PostgreSQL — `pg_trgm` similarity over the GIN trigram index on
 *    `products.name` / `products.sku` (the `%` operator filters candidates by
 *    similarity, ordered by similarity desc). Typo-tolerant, index-backed.
 *  - SQLite (the default test connection) — degrades to an `ilike`-style `LIKE`
 *    rank (exact > prefix > substring), matching the RansackFilter convention.
 *
 * Only active, searchable products are returned (kits/accessory-only products are
 * still valid line items, so they are NOT excluded here). Authorisation is the
 * CALLER's responsibility — the `#[Renderless]` method authorises `products.read`
 * before invoking the service, keeping the service a pure read helper.
 */
class ProductSearchService
{
    /**
     * Minimum trigram similarity for a Postgres hit. Below this the `%` operator
     * rejects the candidate. 0.1 is intentionally permissive so partial / short
     * queries still surface results (the GIN index keeps it cheap).
     */
    private const TRGM_THRESHOLD = 0.1;

    public function __construct(
        private readonly RateResolver $rateResolver,
        private readonly RateCalculator $rateCalculator,
        private readonly AvailabilityService $availabilityService,
    ) {}

    /**
     * Search active products by name / SKU, ranked best-match first.
     *
     * @param  string  $query  The user's query term
     * @param  int|null  $storeId  Store to resolve rate + point availability for (null = no availability chip)
     * @param  int  $limit  Maximum hits to return
     * @return Collection<int, ProductSearchResultData>
     */
    public function search(string $query, ?int $storeId = null, int $limit = 12): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $limit = max(1, min(50, $limit));

        $products = $this->rankedProducts($query, $limit);

        return $products
            ->map(fn (Product $product): ProductSearchResultData => $this->toResult($product, $storeId))
            ->values();
    }

    /**
     * The lightweight catalogue payload the editor's client-side MiniSearch index
     * is built from: every active product with the fields the index searches
     * (`name`, `sku`) plus the stored fields a picker row renders (rate +
     * accessories). Availability is intentionally OMITTED — it is store + date
     * specific and resolved live by the server tier when a row is chosen.
     *
     * Embed this once in the editor (or fetch it once) and hand it to MiniSearch
     * via `addAll()`. Keeping it lean keeps the client index small.
     *
     * @return array<int, array{id: int, name: string, sku: string|null, default_rate: string|null, accessories: array<int, array{id: int, name: string, sku: string|null, ratio: string, included: bool, zero_priced: bool}>}>
     */
    public function catalogueIndex(?int $storeId = null): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $this->eagerLoadAccessories($products);

        return $products
            ->map(fn (Product $product): array => $this->toResult($product, $storeId, withAvailability: false)->toArray())
            ->all();
    }

    /**
     * Resolve the ranked, limited product set for a query on the active driver.
     *
     * @return EloquentCollection<int, Product>
     */
    private function rankedProducts(string $query, int $limit): EloquentCollection
    {
        $builder = Product::query()->where('is_active', true);

        $driver = $builder->getModel()->getConnection()->getDriverName();

        $products = $driver === 'pgsql'
            ? $this->applyTrigramRank($builder, $query)->limit($limit)->get()
            : $this->applyLikeRank($builder, $query)->limit($limit)->get();

        $this->eagerLoadAccessories($products);

        return $products;
    }

    /**
     * Postgres `pg_trgm` ranking: keep rows whose name OR sku is similar to the
     * query (the `%` operator uses the GIN trigram index), ordered by the greater
     * of the two similarities. Bindings are parameterised.
     *
     * @param  Builder<Product>  $builder
     * @return Builder<Product>
     */
    private function applyTrigramRank(Builder $builder, string $query): Builder
    {
        return $builder
            ->whereRaw('set_limit(?)', [self::TRGM_THRESHOLD])
            ->where(function (Builder $q) use ($query): void {
                $q->whereRaw('name % ?', [$query])
                    ->orWhereRaw('coalesce(sku, \'\') % ?', [$query]);
            })
            ->orderByRaw(
                'greatest(similarity(name, ?), similarity(coalesce(sku, \'\'), ?)) desc',
                [$query, $query],
            )
            ->orderBy('name');
    }

    /**
     * SQLite fallback ranking: case-insensitive substring match on name OR sku,
     * ordered exact > prefix > substring (best match first), then name. Mirrors
     * the `ilike` semantics of {@see RansackFilter} on a driver
     * without `ilike` / `pg_trgm`.
     *
     * @param  Builder<Product>  $builder
     * @return Builder<Product>
     */
    private function applyLikeRank(Builder $builder, string $query): Builder
    {
        $escaped = $this->escapeLike(mb_strtolower($query));
        $lower = mb_strtolower($query);

        return $builder
            ->where(function (Builder $q) use ($escaped): void {
                $q->whereRaw('lower(name) like ?', ['%'.$escaped.'%'])
                    ->orWhereRaw('lower(coalesce(sku, \'\')) like ?', ['%'.$escaped.'%']);
            })
            ->orderByRaw(
                'case '.
                'when lower(name) = ? then 0 '.
                'when lower(coalesce(sku, \'\')) = ? then 1 '.
                'when lower(name) like ? then 2 '.
                'when lower(coalesce(sku, \'\')) like ? then 3 '.
                'else 4 end',
                [$lower, $lower, $escaped.'%', $escaped.'%'],
            )
            ->orderBy('name');
    }

    /**
     * Eager-load each product's accessories with their accessory product, so
     * {@see toResult()} reads them without an N+1 per hit.
     *
     * @param  EloquentCollection<int, Product>  $products
     */
    private function eagerLoadAccessories(EloquentCollection $products): void
    {
        $products->load(['accessories' => function ($query): void {
            $query->orderBy('sort_order')->with('accessoryProduct');
        }]);
    }

    /**
     * Build a result DTO for a single product, resolving its default rate and
     * (optionally) point availability for the store.
     */
    private function toResult(Product $product, ?int $storeId, bool $withAvailability = true): ProductSearchResultData
    {
        return new ProductSearchResultData(
            id: $product->id,
            name: $product->name,
            sku: $product->sku,
            default_rate: $this->defaultRate($product, $storeId),
            accessories: $this->accessories($product),
            availability: $withAvailability ? $this->availabilityStatus($product, $storeId) : null,
        );
    }

    /**
     * The product's default per-unit rental rate as a decimal string (RMS major
     * units), or null when nothing resolves. Resolves the highest-priority
     * matching {@see ProductRate} for today and prices a single unit
     * through the rate engine so duration/period strategies are honoured;
     * gracefully falls back to the raw rate price, then to null.
     */
    private function defaultRate(Product $product, ?int $storeId): ?string
    {
        $rate = $this->rateResolver->resolve($product, RateTransactionType::Rental, $storeId);

        if ($rate === null) {
            return null;
        }

        $minor = $rate->price;

        if ($rate->rateDefinition !== null) {
            $now = Carbon::now();

            $context = new CalculationContext(
                unitPriceMinor: $rate->price,
                currency: $this->currencyCode(),
                start: $now,
                end: $now->copy()->addDay(),
                quantity: 1,
                basePeriod: $rate->rateDefinition->base_period,
                strategyConfig: $rate->rateDefinition->strategy_config,
                transactionType: RateTransactionType::Rental,
                storeId: $storeId,
            );

            $minor = $this->rateCalculator->calculate(
                $context,
                $rate->rateDefinition->calculation_strategy->value,
                $rate->rateDefinition->enabled_modifiers,
                $rate->rateDefinition->modifier_configs,
            )->totalMinor();
        }

        return $this->formatMinor($minor);
    }

    /**
     * Map the product's linked accessories to lightweight result DTOs.
     *
     * @return array<int, ProductSearchAccessoryData>
     */
    private function accessories(Product $product): array
    {
        return $product->accessories
            ->map(function (Accessory $accessory): ProductSearchAccessoryData {
                $linked = $accessory->accessoryProduct;

                return new ProductSearchAccessoryData(
                    id: $accessory->accessory_product_id,
                    name: $linked->name,
                    sku: $linked->sku,
                    ratio: (string) $accessory->quantity,
                    included: (bool) $accessory->included,
                    zero_priced: (bool) $accessory->zero_priced,
                );
            })
            ->values()
            ->all();
    }

    /**
     * Point availability status for the product at the store today, collapsed to
     * the editor's three-state chip: `available` (free stock), `reserved` (some
     * stock but none currently free), or `out` (no stock at all). Null when no
     * store was supplied.
     */
    private function availabilityStatus(Product $product, ?int $storeId): ?string
    {
        if ($storeId === null) {
            return null;
        }

        $availability = $this->availabilityService->getAvailability($product->id, $storeId, Carbon::now());

        if ($availability->total_stock <= 0) {
            return 'out';
        }

        return $availability->available > 0 ? 'available' : 'reserved';
    }

    /**
     * Format minor units to a decimal string at the base currency's natural scale.
     */
    private function formatMinor(int $minor): string
    {
        $currency = Currency::of($this->currencyCode());

        return (string) Money::ofMinor($minor, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }

    /**
     * The company base currency, used to format and price the default rate (the
     * picker shows the catalogue rate; the line itself re-prices in the
     * opportunity's currency when added). Never a hardcoded literal.
     */
    private function currencyCode(): string
    {
        $base = settings('company.base_currency', 'GBP');

        return is_string($base) && $base !== '' ? $base : 'GBP';
    }

    /**
     * Escape LIKE wildcards in a user term so `%` / `_` are matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
