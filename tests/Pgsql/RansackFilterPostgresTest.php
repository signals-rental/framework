<?php

use App\Models\Member;
use App\Services\Api\RansackFilter;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class);

beforeEach(function () {
    $this->filter = app(RansackFilter::class);
    $this->allowedFields = ['name', 'account_number', 'description'];
});

it('filters members by cont predicate using ilike', function () {
    Member::factory()->create(['name' => 'Acme Events', 'account_number' => 'ACC-1001']);
    Member::factory()->create(['name' => 'Beta Sound', 'account_number' => 'ACC-2002']);

    $names = $this->filter->apply(
        Member::query(),
        ['name_cont' => 'acme'],
        $this->allowedFields,
    )->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names->first())->toBe('Acme Events');
});

it('filters members by start and end predicates using ilike', function () {
    Member::factory()->create(['name' => 'Admin User', 'account_number' => 'ADMIN-001']);
    Member::factory()->create(['name' => 'Guest', 'account_number' => 'GUEST-999']);

    $starts = $this->filter->apply(
        Member::query(),
        ['account_number_start' => 'ADMIN'],
        $this->allowedFields,
    )->pluck('account_number');

    $ends = $this->filter->apply(
        Member::query(),
        ['account_number_end' => '-001'],
        $this->allowedFields,
    )->pluck('account_number');

    expect($starts)->toContain('ADMIN-001')
        ->and($starts)->not->toContain('GUEST-999')
        ->and($ends)->toContain('ADMIN-001');
});

it('filters members by matches predicate using case-insensitive regex', function () {
    Member::factory()->create(['name' => 'Alice Anderson', 'account_number' => 'ALC-001']);
    Member::factory()->create(['name' => 'Bob Builder', 'account_number' => 'BOB-001']);

    $names = $this->filter->apply(
        Member::query(),
        ['name_matches' => '^Ali'],
        $this->allowedFields,
    )->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names->first())->toBe('Alice Anderson');
});

it('filters members by not_cont predicate using not ilike', function () {
    Member::factory()->create(['name' => 'Test Harness', 'account_number' => 'TST-001']);
    Member::factory()->create(['name' => 'Production Client', 'account_number' => 'PRD-001']);

    $names = $this->filter->apply(
        Member::query(),
        ['name_not_cont' => 'test'],
        $this->allowedFields,
    )->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names->first())->toBe('Production Client');
});
