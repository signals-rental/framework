<?php

use App\Enums\MembershipType;
use App\Http\Traits\FiltersQueries;
use App\Models\CustomView;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Behavioural test double for FiltersQueries — exercises DB-backed helpers.
 */
class FiltersQueriesBehaviorDouble
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = ['name', 'membership_type', 'is_active'];

    /** @var list<string> */
    protected array $allowedSorts = ['name', 'created_at'];

    /** @var array<string, list<string>> */
    protected array $allowedRelationFilters = [];

    /** @var list<string> */
    protected array $allowedIncludes = [];

    /** @var list<string> */
    protected array $defaultIncludes = [];

    protected ?string $customFieldModule = null;

    /** @var array<string, string> */
    protected array $filterAliases = [
        'active' => 'is_active',
    ];

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function callApplyFilters(Builder $query, Request $request): Builder
    {
        return $this->applyFilters($query, $request);
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function callApplySort(Builder $query, Request $request): Builder
    {
        return $this->applySort($query, $request);
    }

    /**
     * @param  Builder<Member>  $query
     * @return array{query: Builder<Member>, view: CustomView|null}
     */
    public function callApplyViewOrFilters(Builder $query, Request $request, string $entityType): array
    {
        return $this->applyViewOrFilters($query, $request, $entityType);
    }

    /**
     * @param  Builder<Member>  $query
     * @return LengthAwarePaginator<int, Member>
     */
    public function callPaginateQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        return $this->paginateQuery($query, $request);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function callTranslateFilterAliases(array $filters): array
    {
        return $this->translateFilterAliases($filters);
    }

    public function callTranslateSortAlias(string $sort): string
    {
        return $this->translateSortAlias($sort);
    }
}

function filtersBehaviorDouble(): FiltersQueriesBehaviorDouble
{
    return new FiltersQueriesBehaviorDouble;
}

it('applyFilters narrows members using request q parameters', function () {
    Member::factory()->create(['name' => 'Filtered One']);
    Member::factory()->create(['name' => 'Other']);

    $request = Request::create('/api/v1/members', 'GET', ['q' => ['name_eq' => 'Filtered One']]);
    $results = filtersBehaviorDouble()
        ->callApplyFilters(Member::query(), $request)
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->name)->toBe('Filtered One');
});

it('translateFilterAliases rewrites alias keys before filtering', function () {
    Member::factory()->create(['name' => 'Active Member', 'is_active' => true]);
    Member::factory()->inactive()->create(['name' => 'Inactive Member']);

    $request = Request::create('/api/v1/members', 'GET', ['q' => ['active_true' => '1']]);
    $names = filtersBehaviorDouble()
        ->callApplyFilters(Member::query(), $request)
        ->pluck('name');

    expect($names)->toContain('Active Member')
        ->and($names)->not->toContain('Inactive Member');
});

it('applySort orders members from the sort query parameter', function () {
    Member::factory()->create(['name' => 'Zulu']);
    Member::factory()->create(['name' => 'Alpha']);

    $request = Request::create('/api/v1/members', 'GET', ['sort' => 'name']);
    $names = filtersBehaviorDouble()
        ->callApplySort(Member::query(), $request)
        ->pluck('name')
        ->all();

    expect(array_search('Alpha', $names, true))->toBeLessThan(array_search('Zulu', $names, true));
});

it('translateSortAlias maps aliased sort fields to real columns', function () {
    expect(filtersBehaviorDouble()->callTranslateSortAlias('-active'))->toBe('-is_active');
});

it('paginateQuery honours per_page and page request values', function () {
    Member::factory()->count(5)->create();

    $request = Request::create('/api/v1/members', 'GET', ['per_page' => 2, 'page' => 2]);
    $page = filtersBehaviorDouble()->callPaginateQuery(Member::query(), $request);

    expect($page->perPage())->toBe(2)
        ->and($page->currentPage())->toBe(2)
        ->and($page->count())->toBe(2)
        ->and($page->total())->toBe(5);
});

it('applyViewOrFilters applies a saved view and returns the resolved view', function () {
    $user = User::factory()->create();
    Member::factory()->create(['name' => 'Org Member', 'membership_type' => MembershipType::Organisation]);
    Member::factory()->create(['name' => 'Contact Member', 'membership_type' => MembershipType::Contact]);

    $view = CustomView::factory()->create([
        'user_id' => $user->id,
        'entity_type' => 'members',
        'columns' => ['name'],
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation', 'logic' => 'and'],
        ],
        'sort_column' => 'name',
        'sort_direction' => 'asc',
    ]);

    $request = Request::create('/api/v1/members', 'GET', ['view_id' => $view->id]);
    $request->setUserResolver(fn () => $user);

    ['query' => $query, 'view' => $resolved] = filtersBehaviorDouble()
        ->callApplyViewOrFilters(Member::query(), $request, 'members');

    $names = $query->pluck('name');

    expect($resolved?->id)->toBe($view->id)
        ->and($names)->toHaveCount(1)
        ->and($names->first())->toBe('Org Member');
});

it('applyViewOrFilters aborts when view_id does not resolve', function () {
    $user = User::factory()->create();
    $request = Request::create('/api/v1/members', 'GET', ['view_id' => 999999]);
    $request->setUserResolver(fn () => $user);

    filtersBehaviorDouble()->callApplyViewOrFilters(Member::query(), $request, 'members');
})->throws(HttpException::class);

it('applyViewOrFilters coerces a non-array q parameter to an empty filter set', function () {
    // When a view is resolved but `q` arrives as a scalar string (not the expected
    // array shape), the explicit-filter set is reset to [] (line 141) so the view's
    // own filters apply unmodified.
    $user = User::factory()->create();
    Member::factory()->create(['name' => 'Org Member', 'membership_type' => MembershipType::Organisation]);
    Member::factory()->create(['name' => 'Contact Member', 'membership_type' => MembershipType::Contact]);

    $view = CustomView::factory()->create([
        'user_id' => $user->id,
        'entity_type' => 'members',
        'columns' => ['name'],
        'filters' => [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation', 'logic' => 'and'],
        ],
    ]);

    // q is a bare string rather than an array.
    $request = Request::create('/api/v1/members', 'GET', ['view_id' => $view->id, 'q' => 'not-an-array']);
    $request->setUserResolver(fn () => $user);

    ['query' => $query, 'view' => $resolved] = filtersBehaviorDouble()
        ->callApplyViewOrFilters(Member::query(), $request, 'members');

    $names = $query->pluck('name');

    expect($resolved?->id)->toBe($view->id)
        ->and($names)->toHaveCount(1)
        ->and($names->first())->toBe('Org Member');
});

it('translateFilterAliases rewrites a bare alias key with no predicate suffix', function () {
    // `active` (the exact alias, no `_predicate` suffix) maps directly to the real
    // `is_active` column via the exact-match branch (lines 278-281), distinct from
    // the `active_true` predicate-suffix path.
    $translated = filtersBehaviorDouble()->callTranslateFilterAliases(['active' => '1']);

    expect($translated)->toHaveKey('is_active')
        ->and($translated)->not->toHaveKey('active')
        ->and($translated['is_active'])->toBe('1');
});
