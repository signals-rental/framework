<?php

use App\Http\Traits\FiltersQueries;
use Illuminate\Http\Request;

/**
 * Test double exposing the protected sort/include helpers of FiltersQueries.
 *
 * Only the request-driven sort resolution and include-path conversion are
 * exercised here; those methods are pure (no DB / RansackFilter). The double
 * declares every property the trait reads so PHPStan can analyse the trait
 * against a concrete consumer, mirroring the declarations real API
 * controllers (e.g. ProductController) provide.
 */
class FiltersQueriesTestDouble
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [];

    /** @var list<string> */
    protected array $allowedSorts = [];

    /** @var array<string, list<string>> */
    protected array $allowedRelationFilters = [];

    /** @var list<string> */
    protected array $allowedIncludes;

    /** @var list<string> */
    protected array $defaultIncludes = [];

    protected ?string $customFieldModule = null;

    /** @var array<string, string> */
    protected array $filterAliases = [];

    /**
     * @param  list<string>  $allowedIncludes
     */
    public function __construct(array $allowedIncludes = [])
    {
        $this->allowedIncludes = $allowedIncludes;
    }

    public function callResolveSortParam(Request $request): ?string
    {
        return $this->resolveSortParam($request);
    }

    public function callHasExplicitSort(Request $request): bool
    {
        return $this->hasExplicitSort($request);
    }

    public function callResolveIncludeRelation(string $name): ?string
    {
        return $this->resolveIncludeRelation($name);
    }

    public function callSnakeIncludePath(string $relation): string
    {
        return $this->snakeIncludePath($relation);
    }
}

/**
 * @param  list<string>  $allowedIncludes
 */
function filtersQueriesDouble(array $allowedIncludes = []): FiltersQueriesTestDouble
{
    return new FiltersQueriesTestDouble($allowedIncludes);
}

describe('resolveSortParam — q[s][] array-sort form', function () {
    it('resolves the array form ascending to a bare field', function () {
        $request = Request::create('/api/v1/products', 'GET', ['q' => ['s' => ['name asc']]]);

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBe('name');
    });

    it('resolves the array form descending to a -prefixed field', function () {
        $request = Request::create('/api/v1/products', 'GET', ['q' => ['s' => ['created_at desc']]]);

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBe('-created_at');
    });

    it('takes the first entry of a multi-element array-sort form', function () {
        $request = Request::create('/api/v1/products', 'GET', ['q' => ['s' => ['name desc', 'created_at asc']]]);

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBe('-name');
    });

    it('defaults to ascending when the array entry omits a direction', function () {
        $request = Request::create('/api/v1/products', 'GET', ['q' => ['s' => ['name']]]);

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBe('name');
    });

    it('returns null for an empty array-sort form', function () {
        $request = Request::create('/api/v1/products', 'GET', ['q' => ['s' => []]]);

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBeNull();
    });

    it('returns null when no sort parameter is present', function () {
        $request = Request::create('/api/v1/products', 'GET');

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBeNull();
    });

    it('prefers the dedicated sort parameter over the q[s] array form', function () {
        $request = Request::create('/api/v1/products', 'GET', [
            'sort' => '-name',
            'q' => ['s' => ['created_at asc']],
        ]);

        expect(filtersQueriesDouble()->callResolveSortParam($request))->toBe('-name');
    });
});

describe('hasExplicitSort', function () {
    it('is true when a q[s][] array sort is supplied', function () {
        $request = Request::create('/api/v1/products', 'GET', ['q' => ['s' => ['name desc']]]);

        expect(filtersQueriesDouble()->callHasExplicitSort($request))->toBeTrue();
    });

    it('is false when no sort is supplied', function () {
        $request = Request::create('/api/v1/products', 'GET');

        expect(filtersQueriesDouble()->callHasExplicitSort($request))->toBeFalse();
    });
});

describe('resolveIncludeRelation / snakeIncludePath — dotted include paths', function () {
    it('matches a dotted relation path by its exact whitelisted name', function () {
        $double = filtersQueriesDouble(['accessories.accessoryProduct']);

        expect($double->callResolveIncludeRelation('accessories.accessoryProduct'))
            ->toBe('accessories.accessoryProduct');
    });

    it('matches a dotted relation path by its snake_case alias', function () {
        $double = filtersQueriesDouble(['accessories.accessoryProduct']);

        expect($double->callResolveIncludeRelation('accessories.accessory_product'))
            ->toBe('accessories.accessoryProduct');
    });

    it('matches a single-segment relation by its snake_case alias', function () {
        $double = filtersQueriesDouble(['stockLevels']);

        expect($double->callResolveIncludeRelation('stock_levels'))->toBe('stockLevels');
    });

    it('returns null for an include name that is not whitelisted', function () {
        $double = filtersQueriesDouble(['accessories.accessoryProduct']);

        expect($double->callResolveIncludeRelation('costs'))->toBeNull();
    });

    it('snake-cases each dotted segment while preserving the dot separators', function () {
        $double = filtersQueriesDouble();

        expect($double->callSnakeIncludePath('accessories.accessoryProduct'))
            ->toBe('accessories.accessory_product')
            ->and($double->callSnakeIncludePath('participants.member'))
            ->toBe('participants.member')
            ->and($double->callSnakeIncludePath('stockLevels'))
            ->toBe('stock_levels');
    });
});
