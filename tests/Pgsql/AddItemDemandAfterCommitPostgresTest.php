<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| #8 — Adding a line item must not poison the Verbs transaction on Postgres
|--------------------------------------------------------------------------
|
| Regression: the DemandObserver enqueued the ShouldBeUnique
| RecalculateAvailabilityJob INSIDE the Verbs DB::transaction that wraps an
| event commit. Acquiring the job's unique lock INSERTs into `cache_locks`,
| which on PostgreSQL fails on conflict and aborts the open transaction with
| `SQLSTATE 25P02 current transaction is aborted` — so every later statement in
| the commit throws and the whole "add item" flow 500s. SQLite tolerated it,
| which is why the SQLite lane stayed green.
|
| The fix defers the dispatch to DB::afterCommit so the lock query runs OUTSIDE
| the Verbs transaction. These tests prove, on a REAL Postgres backend with the
| database cache lock store engaged, that adding an item does not throw and that
| the demand row + recompute are produced.
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('adds a line item without poisoning the Verbs transaction (25P02) on Postgres', function () {
    // Engage the DATABASE cache lock store so the ShouldBeUnique lock acquisition
    // INSERTs into cache_locks — the exact statement that aborted the transaction
    // pre-fix. The recompute runs synchronously (sync queue) once dispatched.
    config(['cache.default' => 'database', 'queue.default' => 'sync']);

    $from = Carbon::parse('2026-09-01T09:00:00Z');
    $to = Carbon::parse('2026-09-05T17:00:00Z');
    $product = Product::factory()->rental()->bulk()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG add-item slice',
        'store_id' => $this->store->id,
        'starts_at' => $from->toIso8601String(),
        'ends_at' => $to->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    // Pre-fix this threw QueryException(25P02); post-fix it completes cleanly.
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();

    // The demand row for the new line was written (the inner Verbs transaction
    // committed cleanly rather than aborting with 25P02).
    $demand = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();
    expect((int) $demand->quantity)->toBe(2)
        ->and($demand->product_id)->toBe($product->id);
});
