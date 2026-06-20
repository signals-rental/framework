<?php

use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Job Planner page (C1)
|--------------------------------------------------------------------------
|
| A Gantt / Overlap calendar of OPPORTUNITIES (jobs) across dates. One row per
| opportunity, bars spanning the job window with Delivery / In-Use / Collection
| bands + Customer Collecting / Customer Returning badges, coloured by status.
| Filters: date · view (1w/2w/1m/monthly) · include (Quotation/Order) · store ·
| search. JOBS / ORDERS / QUOTES count badges. Gated on opportunities.access.
|
| Tests run under the Phase-3 cadence and executed for this workstream.
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['name' => 'Main', 'is_default' => true]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['opportunities.access']);

    Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Helper: an opportunity that overlaps the default 2-week window (today + 13d).
 *
 * @param  array<string, mixed>  $attributes
 */
function plannerJob(array $attributes = []): Opportunity
{
    return Opportunity::factory()->create(array_merge([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ], $attributes));
}

it('renders the planner page for a user with opportunities.access', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->assertSet('viewPeriod', '2w')
        ->assertSet('mode', 'gantt')
        ->assertSet('storeId', 0)
        ->assertSet('date', '2026-06-20')
        ->assertOk();
});

it('forbids the page for a user without opportunities.access', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('planner.index')->assertForbidden();
});

it('shows a job bar for a seeded opportunity in the window', function () {
    $job = Opportunity::factory()->quotation()->create([
        'subject' => 'Summer Festival Main Stage',
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $rows = Volt::test('planner.index')->get('rows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($job->id)
        ->and($rows[0]['subject'])->toBe('Summer Festival Main Stage')
        ->and($rows[0]['width'])->toBeGreaterThan(0);
});

it('excludes opportunities outside the window', function () {
    Opportunity::factory()->quotation()->create([
        'starts_at' => Carbon::parse('2026-09-01 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-09-05 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    expect(Volt::test('planner.index')->get('rows'))->toHaveCount(0);
});

it('excludes Draft-state opportunities (only quotations and orders are jobs)', function () {
    // Default factory state is Draft.
    plannerJob();

    $this->actingAs($this->user);

    expect(Volt::test('planner.index')->get('rows'))->toHaveCount(0);
});

it('computes JOBS / ORDERS / QUOTES counts for the window', function () {
    Opportunity::factory()->quotation()->count(2)->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-23 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-24 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $counts = Volt::test('planner.index')->get('counts');

    expect($counts['jobs'])->toBe(3)
        ->and($counts['quotes'])->toBe(2)
        ->and($counts['orders'])->toBe(1);
});

it('narrows results with the include-quotations / include-orders toggles', function () {
    Opportunity::factory()->quotation()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    // Orders only.
    Volt::test('planner.index')
        ->set('includeQuotations', false)
        ->set('includeOrders', true)
        ->assertCount('rows', 1)
        ->assertSet('counts', fn ($c) => $c['orders'] === 1 && $c['quotes'] === 0);

    // Neither included → no rows.
    Volt::test('planner.index')
        ->set('includeQuotations', false)
        ->set('includeOrders', false)
        ->assertCount('rows', 0);
});

it('filters by store', function () {
    $other = Store::factory()->create(['name' => 'Depot']);

    Opportunity::factory()->order()->create([
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'store_id' => $other->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->set('storeId', $this->store->id)
        ->assertCount('rows', 1);
});

it('searches across subject, number and member name', function () {
    $member = Member::factory()->create(['name' => 'Festival Hire Co']);

    Opportunity::factory()->order()->create([
        'subject' => 'Wedding Marquee',
        'member_id' => $member->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'subject' => 'Corporate Gala',
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    // By subject.
    Volt::test('planner.index')->set('search', 'Marquee')->assertCount('rows', 1);
    // By member name.
    Volt::test('planner.index')->set('search', 'Festival Hire')->assertCount('rows', 1);
    // No match.
    Volt::test('planner.index')->set('search', 'Nonexistent')->assertCount('rows', 0);
});

it('toggles between gantt and overlap modes and lane-packs in overlap', function () {
    // Two overlapping orders → in overlap mode they must occupy different lanes.
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-23 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-27 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    // Gantt mode carries no lane metadata.
    $ganttRows = Volt::test('planner.index')->set('mode', 'gantt')->get('rows');
    expect($ganttRows)->toHaveCount(2)
        ->and($ganttRows[0])->not->toHaveKey('lane');

    // Overlap mode packs the two overlapping jobs into distinct lanes.
    $overlapRows = Volt::test('planner.index')->set('mode', 'overlap')->get('rows');
    expect($overlapRows)->toHaveCount(2)
        ->and($overlapRows[0])->toHaveKey('lane')
        ->and($overlapRows[0]['lanes'])->toBe(2);

    $lanes = array_column($overlapRows, 'lane');
    expect($lanes)->toEqualCanonicalizing([0, 1]);
});

it('builds Delivery / In-Use / Collection sub-bands and customer badges', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
        'deliver_starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'deliver_ends_at' => Carbon::parse('2026-06-22 12:00:00', 'UTC'),
        'show_starts_at' => Carbon::parse('2026-06-23 09:00:00', 'UTC'),
        'show_ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
        'collect_starts_at' => Carbon::parse('2026-06-26 09:00:00', 'UTC'),
        'collect_ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
        'customer_collecting' => true,
        'customer_returning' => true,
    ]);

    $this->actingAs($this->user);

    $row = Volt::test('planner.index')->get('rows')[0];

    $bandKeys = array_column($row['bands'], 'key');

    expect($bandKeys)->toContain('delivery')
        ->and($bandKeys)->toContain('in-use')
        ->and($bandKeys)->toContain('collection')
        ->and($row['customer_collecting'])->toBeTrue()
        ->and($row['customer_returning'])->toBeTrue();
});

it('steps the window forward, back and home', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->assertSet('date', '2026-06-20')
        ->call('nextPeriod')
        ->assertSet('date', '2026-07-04') // +14 days
        ->call('previousPeriod')
        ->assertSet('date', '2026-06-20')
        ->call('nextPeriod')
        ->call('today')
        ->assertSet('date', '2026-06-20');
});
