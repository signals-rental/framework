<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Services\Api\RansackFilter;

beforeEach(function () {
    $this->filter = app(RansackFilter::class);
    $this->allowedFields = ['name', 'membership_type', 'is_active', 'created_at'];
});

it('filters members by eq predicate', function () {
    Member::factory()->create(['name' => 'Alpha Events']);
    Member::factory()->create(['name' => 'Beta Sound']);

    $ids = $this->filter->apply(
        Member::query(),
        ['name_eq' => 'Alpha Events'],
        $this->allowedFields,
    )->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and(Member::find($ids->first())?->name)->toBe('Alpha Events');
});

it('filters members by not_eq predicate', function () {
    $alpha = Member::factory()->create(['name' => 'Alpha Events']);
    Member::factory()->create(['name' => 'Beta Sound']);

    $ids = $this->filter->apply(
        Member::query(),
        ['name_not_eq' => 'Alpha Events'],
        $this->allowedFields,
    )->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->not->toBe($alpha->id);
});

it('filters members by in predicate', function () {
    $contact = Member::factory()->create(['membership_type' => MembershipType::Contact]);
    Member::factory()->create(['membership_type' => MembershipType::Organisation]);
    Member::factory()->create(['membership_type' => MembershipType::Venue]);

    $ids = $this->filter->apply(
        Member::query(),
        ['membership_type_in' => 'contact,venue'],
        $this->allowedFields,
    )->pluck('id');

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($contact->id);
});

it('filters members by true and false predicates', function () {
    $active = Member::factory()->create(['is_active' => true]);
    $inactive = Member::factory()->inactive()->create();

    $activeIds = $this->filter->apply(
        Member::query(),
        ['is_active_true' => '1'],
        $this->allowedFields,
    )->pluck('id');

    $inactiveIds = $this->filter->apply(
        Member::query(),
        ['is_active_false' => '1'],
        $this->allowedFields,
    )->pluck('id');

    expect($activeIds)->toContain($active->id)
        ->and($activeIds)->not->toContain($inactive->id)
        ->and($inactiveIds)->toContain($inactive->id)
        ->and($inactiveIds)->not->toContain($active->id);
});

it('filters members by null and not_null predicates', function () {
    Member::factory()->create(['name' => 'Named Member']);
    Member::factory()->create(['name' => '']);

    $present = $this->filter->apply(
        Member::query(),
        ['name_present' => '1'],
        $this->allowedFields,
    )->pluck('name');

    $blank = $this->filter->apply(
        Member::query(),
        ['name_blank' => '1'],
        $this->allowedFields,
    )->pluck('name');

    expect($present)->toContain('Named Member')
        ->and($present)->not->toContain('')
        ->and($blank)->toContain('');
});

it('sorts members ascending and descending via applySort', function () {
    Member::factory()->create(['name' => 'Zulu Corp']);
    Member::factory()->create(['name' => 'Alpha Inc']);

    $asc = $this->filter->applySort(Member::query(), 'name', $this->allowedFields)->pluck('name')->all();
    $desc = $this->filter->applySort(Member::query(), '-name', $this->allowedFields)->pluck('name')->all();

    expect(array_search('Alpha Inc', $asc, true))->toBeLessThan(array_search('Zulu Corp', $asc, true))
        ->and(array_search('Zulu Corp', $desc, true))->toBeLessThan(array_search('Alpha Inc', $desc, true));
});

it('combines multiple sqlite-safe predicates to narrow the result set', function () {
    Member::factory()->create([
        'name' => 'Active Contact',
        'membership_type' => MembershipType::Contact,
        'is_active' => true,
    ]);
    Member::factory()->create([
        'name' => 'Inactive Contact',
        'membership_type' => MembershipType::Contact,
        'is_active' => false,
    ]);
    Member::factory()->create([
        'name' => 'Active Org',
        'membership_type' => MembershipType::Organisation,
        'is_active' => true,
    ]);

    $ids = $this->filter->apply(
        Member::query(),
        [
            'membership_type_eq' => 'contact',
            'is_active_true' => '1',
        ],
        $this->allowedFields,
    )->pluck('name');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe('Active Contact');
});
