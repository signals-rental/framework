<?php

use App\Models\User;
use App\Services\Api\RansackFilter;

uses(Tests\TestCase::class);

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
