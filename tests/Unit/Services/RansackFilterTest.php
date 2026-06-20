<?php

use App\Models\Opportunity;
use App\Models\ProductRate;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\Api\RansackFilter;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->filter = new RansackFilter;
    $this->allowedFields = ['name', 'email', 'is_active', 'created_at'];
});

// ─── Predicate: eq ───────────────────────────────────────────────

it('applies eq predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_eq' => 'Alice'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" = \'Alice\'');
});

// ─── Predicate: not_eq ───────────────────────────────────────────

it('applies not_eq predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_not_eq' => 'Bob'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" != \'Bob\'');
});

// ─── Predicate: lt ───────────────────────────────────────────────

it('applies lt predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['created_at_lt' => '2025-01-01'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"created_at" < \'2025-01-01\'');
});

// ─── Predicate: lteq ────────────────────────────────────────────

it('applies lteq predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['created_at_lteq' => '2025-12-31'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"created_at" <= \'2025-12-31\'');
});

// ─── Predicate: gt ───────────────────────────────────────────────

it('applies gt predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['created_at_gt' => '2024-06-01'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"created_at" > \'2024-06-01\'');
});

// ─── Predicate: gteq ────────────────────────────────────────────

it('applies gteq predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['created_at_gteq' => '2024-01-01'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"created_at" >= \'2024-01-01\'');
});

// ─── Predicate: cont ────────────────────────────────────────────

it('applies cont predicate with ilike', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_cont' => 'ali'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" ilike \'%ali%\'');
});

// ─── Predicate: not_cont ────────────────────────────────────────

it('applies not_cont predicate with not ilike', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_not_cont' => 'test'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" not ilike \'%test%\'');
});

// ─── Predicate: start ───────────────────────────────────────────

it('applies start predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['email_start' => 'admin'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"email" ilike \'admin%\'');
});

// ─── Predicate: end ─────────────────────────────────────────────

it('applies end predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['email_end' => '.com'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"email" ilike \'%.com\'');
});

// ─── Predicate: null ────────────────────────────────────────────

it('applies null predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['email_null' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"email" is null');
});

// ─── Predicate: not_null ────────────────────────────────────────

it('applies not_null predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['email_not_null' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"email" is not null');
});

// ─── Predicate: present ─────────────────────────────────────────

it('applies present predicate (not null and not empty)', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_present' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)
        ->toContain('"name" is not null')
        ->toContain('"name" != \'\'');
});

// ─── Predicate: blank ───────────────────────────────────────────

it('applies blank predicate (null or empty)', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_blank' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)
        ->toContain('"name" is null')
        ->toContain('"name" = \'\'');
});

// ─── Predicate: in ──────────────────────────────────────────────

it('applies in predicate with comma-separated values', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_in' => 'Alice,Bob,Charlie'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" in (\'Alice\', \'Bob\', \'Charlie\')');
});

// ─── Predicate: not_in ──────────────────────────────────────────

it('applies not_in predicate with comma-separated values', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_not_in' => 'Alice,Bob'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" not in (\'Alice\', \'Bob\')');
});

// ─── Predicate: true ────────────────────────────────────────────

it('applies true predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['is_active_true' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    // Laravel's query builder serialises boolean true as 1 in raw SQL
    expect($sql)->toContain('"is_active" = 1');
});

// ─── Predicate: false ───────────────────────────────────────────

it('applies false predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['is_active_false' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    // Laravel's query builder serialises boolean false as 0 in raw SQL
    expect($sql)->toContain('"is_active" = 0');
});

// ─── Security: disallowed fields ─────────────────────────────────

it('ignores filters on fields not in allowedFields', function () {
    $query = $this->filter->apply(
        User::query(),
        ['password_eq' => 'secret', 'name_eq' => 'Alice'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)
        ->not->toContain('password')
        ->toContain('"name" = \'Alice\'');
});

// ─── Empty filters ──────────────────────────────────────────────

it('returns unmodified query for empty filter array', function () {
    $baseQuery = User::query();
    $baseSql = $baseQuery->toRawSql();

    $query = $this->filter->apply(
        User::query(),
        [],
        $this->allowedFields,
    );

    expect($query->toRawSql())->toBe($baseSql);
});

// ─── Invalid filter keys ────────────────────────────────────────

it('ignores filter keys that do not match any predicate', function () {
    $baseQuery = User::query();
    $baseSql = $baseQuery->toRawSql();

    $query = $this->filter->apply(
        User::query(),
        ['name' => 'Alice', 'invalid_predicate_xyz' => 'value'],
        $this->allowedFields,
    );

    expect($query->toRawSql())->toBe($baseSql);
});

// ─── Predicate matching: longest suffix first ───────────────────

it('matches not_cont before cont for field names ending in not', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_not_cont' => 'test'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    // Should parse as field=name, predicate=not_cont (not field=name_not, predicate=cont)
    expect($sql)->toContain('not ilike')
        ->toContain('test');
});

it('matches not_null before null', function () {
    $query = $this->filter->apply(
        User::query(),
        ['email_not_null' => '1'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"email" is not null');
});

// ─── Sort: ascending ────────────────────────────────────────────

it('applies ascending sort', function () {
    $query = $this->filter->applySort(
        User::query(),
        'name',
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('order by "name" asc');
});

// ─── Sort: descending ───────────────────────────────────────────

it('applies descending sort with leading dash', function () {
    $query = $this->filter->applySort(
        User::query(),
        '-created_at',
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('order by "created_at" desc');
});

// ─── Sort: disallowed field ─────────────────────────────────────

it('ignores sort on fields not in allowedFields', function () {
    $baseQuery = User::query();
    $baseSql = $baseQuery->toRawSql();

    $query = $this->filter->applySort(
        User::query(),
        'password',
        $this->allowedFields,
    );

    expect($query->toRawSql())->toBe($baseSql);
});

// ─── Sort: empty string ─────────────────────────────────────────

it('returns unmodified query for empty sort string', function () {
    $baseQuery = User::query();
    $baseSql = $baseQuery->toRawSql();

    $query = $this->filter->applySort(
        User::query(),
        '',
        $this->allowedFields,
    );

    expect($query->toRawSql())->toBe($baseSql);
});

// ─── Multiple filters combined ──────────────────────────────────

it('applies multiple filters together', function () {
    $query = $this->filter->apply(
        User::query(),
        [
            'name_cont' => 'alice',
            'is_active_true' => '1',
            'created_at_gteq' => '2024-01-01',
        ],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)
        ->toContain('ilike')
        ->toContain('alice')
        ->toContain('"is_active" = 1')
        ->toContain('"created_at" >= \'2024-01-01\'');
});

// ─── Security: ILIKE wildcard escaping ──────────────────────────

it('escapes ILIKE wildcard characters in cont predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_cont' => '100%_match'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    // Wildcards must be escaped to prevent injection
    expect($sql)->toContain('100\\%\\_match');
});

it('escapes ILIKE wildcard characters in start predicate', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_start' => 'test%'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('test\\%');
});

// ─── Predicate: matches (PostgreSQL regex) ─────────────────────

it('applies matches predicate using PostgreSQL case-insensitive regex', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_matches' => '^Ali'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" ~* \'^Ali\'');
});

it('parses matches predicate correctly for compound field names', function () {
    $query = $this->filter->apply(
        User::query(),
        ['created_at_matches' => '2025'],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"created_at" ~* \'2025\'');
});

// ─── Predicate: in with array values ───────────────────────────

it('applies in predicate with array values', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_in' => ['Alice', 'Bob']],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" in (\'Alice\', \'Bob\')');
});

it('applies not_in predicate with array values', function () {
    $query = $this->filter->apply(
        User::query(),
        ['name_not_in' => ['Alice', 'Bob']],
        $this->allowedFields,
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"name" not in (\'Alice\', \'Bob\')');
});

// ─── Relationship filtering ────────────────────────────────────

it('applies relationship filter when relation is allowed', function () {
    $query = $this->filter->apply(
        User::query(),
        ['roles.name_eq' => 'admin'],
        $this->allowedFields,
        allowedRelationFilters: ['roles' => ['name']],
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('exists')
        ->toContain('"name" = \'admin\'');
});

it('ignores relationship filter when relation is not allowed', function () {
    $baseQuery = User::query();
    $baseSql = $baseQuery->toRawSql();

    $query = $this->filter->apply(
        User::query(),
        ['roles.name_eq' => 'admin'],
        $this->allowedFields,
        allowedRelationFilters: [],
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('applies predicate within relationship filter', function () {
    $query = $this->filter->apply(
        User::query(),
        ['roles.name_cont' => 'adm'],
        $this->allowedFields,
        allowedRelationFilters: ['roles' => ['name']],
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('exists')
        ->toContain('ilike')
        ->toContain('adm');
});

it('ignores relationship filter when the relation column is not allowed', function () {
    $baseSql = User::query()->toRawSql();

    // 'roles' relation is allowed but only its 'id' column — 'name' must be rejected
    // (prevents filtering on arbitrary related columns / 500s on bad columns).
    $query = $this->filter->apply(
        User::query(),
        ['roles.name_eq' => 'admin'],
        $this->allowedFields,
        allowedRelationFilters: ['roles' => ['id']],
    );

    expect($query->toRawSql())->toBe($baseSql);
});

// ─── Custom field filtering (no DB needed) ──────────────────────

it('ignores custom field filters when customFieldModule is null', function () {
    $baseSql = User::query()->toRawSql();

    $query = $this->filter->apply(
        User::query(),
        ['cf.something_eq' => 'value'],
        $this->allowedFields,
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('ignores custom field filter key that has no parseable predicate', function () {
    $baseSql = User::query()->toRawSql();

    $query = $this->filter->apply(
        User::query(),
        ['cf.nopredicate' => 'value'],
        $this->allowedFields,
        customFieldModule: 'App\\Models\\User',
    );

    expect($query->toRawSql())->toBe($baseSql);
});

it('ignores filter key that is only a predicate suffix with no field name', function () {
    $baseSql = User::query()->toRawSql();

    $query = $this->filter->apply(
        User::query(),
        ['_eq' => 'value'],
        $this->allowedFields,
    );

    expect($query->toRawSql())->toBe($baseSql);
});

// ─── Backed-enum coercion: string-backed enum columns ───────────

it('coerces a string-backed enum value to its canonical backing value (case-insensitive)', function () {
    // ProductRate::transaction_type casts to the string-backed RateTransactionType.
    $query = $this->filter->apply(
        ProductRate::query(),
        ['transaction_type_eq' => 'ReNtAl'],
        ['transaction_type'],
    );

    expect($query->toRawSql())->toContain('"transaction_type" = \'rental\'');
});

it('leaves an unrecognised string on a string-backed enum column untouched (zero-result, not error)', function () {
    $query = $this->filter->apply(
        ProductRate::query(),
        ['transaction_type_eq' => 'lease'],
        ['transaction_type'],
    );

    // Unknown value passes through verbatim so the query simply matches nothing.
    expect($query->toRawSql())->toContain('"transaction_type" = \'lease\'');
});

// ─── Backed-enum coercion: int-backed enum columns (PR-12) ──────

it('coerces an int-backed enum case name to its integer backing value', function () {
    // StockTransaction::transaction_type casts to the int-backed TransactionType.
    $query = $this->filter->apply(
        StockTransaction::query(),
        ['transaction_type_eq' => 'Opening'],
        ['transaction_type'],
    );

    // TransactionType::Opening->value === 1
    expect($query->toRawSql())->toContain('"transaction_type" = 1');
});

it('coerces an int-backed enum numeric backing value supplied as a string', function () {
    $query = $this->filter->apply(
        StockTransaction::query(),
        ['transaction_type_eq' => '7'],
        ['transaction_type'],
    );

    // '7' matches TransactionType::Sell->value (7).
    expect($query->toRawSql())->toContain('"transaction_type" = 7');
});

it('does not over-match int-backed enum rows for an unrecognised string (PR-12 regression)', function () {
    $query = $this->filter->apply(
        StockTransaction::query(),
        ['transaction_type_eq' => 'not-a-type'],
        ['transaction_type'],
    );

    $sql = $query->toRawSql();

    // Regression: an unrecognised string must NOT be DB-cast to 0 (which would
    // over-match). It resolves to a non-numeric sentinel that can never equal a
    // stored integer, yielding an empty result set.
    expect($sql)->toContain('__signals_no_match__');
    expect($sql)->not->toContain('"transaction_type" = 0');
    expect($sql)->not->toContain('"transaction_type" = \'0\'');
});

it('does not over-match int-backed enum rows for an unrecognised value in an in filter (PR-12 regression)', function () {
    $query = $this->filter->apply(
        StockTransaction::query(),
        ['transaction_type_in' => 'Opening,not-a-type'],
        ['transaction_type'],
    );

    $sql = $query->toRawSql();

    // The valid name resolves to its int; the unrecognised one becomes the
    // sentinel rather than collapsing to 0.
    expect($sql)
        ->toContain('__signals_no_match__')
        ->toContain('1');
    expect($sql)->not->toContain('in (0,');
    expect($sql)->not->toContain(', 0)');
});

it('passes an int-backed enum integer that matches no case through untouched', function () {
    // An explicit integer with no matching case is a legitimate (if empty)
    // query — it is NOT replaced with the sentinel, since no 0-cast hazard exists.
    $query = $this->filter->apply(
        StockTransaction::query(),
        ['transaction_type_eq' => 99],
        ['transaction_type'],
    );

    $sql = $query->toRawSql();

    expect($sql)->toContain('"transaction_type" = 99');
    expect($sql)->not->toContain('__signals_no_match__');
});

// ─── JSONB array columns (R-A master M4 / B4) ───────────────────────

it('routes a cont predicate on a JSONB array column through whereJsonContains', function () {
    $query = $this->filter->apply(
        Opportunity::query(),
        ['tag_list_cont' => 'vip'],
        ['tag_list'],
    );

    $sql = $query->toRawSql();

    // A JSON membership test, NOT a scalar ilike against the jsonb array.
    expect($sql)
        ->toContain('tag_list')
        ->toContain('vip');
    expect($sql)->not->toContain('ilike');
});

it('routes an eq predicate on a JSONB array column through whereJsonContains', function () {
    $query = $this->filter->apply(
        Opportunity::query(),
        ['tag_list_eq' => 'rush'],
        ['tag_list'],
    );

    $sql = $query->toRawSql();
    expect($sql)->toContain('tag_list');
    expect($sql)->not->toContain('"tag_list" = ');
});

it('routes an in predicate on a JSONB array column through OR membership tests', function () {
    $query = $this->filter->apply(
        Opportunity::query(),
        ['tag_list_in' => 'vip,rush'],
        ['tag_list'],
    );

    $sql = $query->toRawSql();

    expect($sql)
        ->toContain('vip')
        ->toContain('rush');
    expect($sql)->not->toContain('ilike');
});

it('keeps scalar boolean filtering for non-JSON columns alongside the JSON branch', function () {
    // has_shortage is a boolean (not JSON), so q[has_shortage_true] must still
    // use the scalar boolean path, not the JSON branch.
    $query = $this->filter->apply(
        Opportunity::query(),
        ['has_shortage_true' => '1'],
        ['has_shortage'],
    );

    expect($query->toRawSql())->toContain('"has_shortage" = ');
});
