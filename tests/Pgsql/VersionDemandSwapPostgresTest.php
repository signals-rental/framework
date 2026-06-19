<?php

use App\Actions\Opportunities\ActivateVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Enums\OpportunityStatus;
use App\Enums\VersionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL quote-version demand-swap lane
|--------------------------------------------------------------------------
|
| Proves the demand SWAP on version activation (§8.9) holds on PostgreSQL,
| where the serialised-asset exclusion constraint (excl_demands_asset_period)
| is enforced. The previously-active version's demands MUST be released before
| the new active version's demands are synced, otherwise two active demands for
| the same asset/window would violate the constraint (23P01). Runs against the
| dedicated pgsql_testing connection; SKIPs when Postgres is unreachable.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

it('swaps serialised-asset demands between versions without violating the exclusion constraint', function () {
    // A serialised product with one physical asset both versions claim against —
    // the same asset over the same window can only hold ONE active demand at a
    // time, so a clean release-before-sync is mandatory.
    $product = Product::factory()->rental()->serialised()->create();
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG version swap',
        'store_id' => $this->store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name, 'item_id' => $product->id, 'item_type' => Product::class, 'quantity' => '1',
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    // Reserve so demands become active.
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    $v1Items = OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->items->pluck('id');
    expect(Demand::query()->whereIn('source_id', $v1Items)->where('is_active', true)->count())->toBeGreaterThan(0);

    // Create an alternative claiming the SAME product/asset/window; it becomes
    // active. The swap must release v1's demands first — if it did not, syncing
    // v2's asset demand against the same window would throw 23P01.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    $opportunity->refresh();

    expect(Demand::query()->whereIn('source_id', $v1Items)->where('is_active', true)->count())->toBe(0);
    $v2Items = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items->pluck('id');
    expect(Demand::query()->whereIn('source_id', $v2Items)->where('is_active', true)->count())->toBeGreaterThan(0);

    // Switch back to v1: the swap releases v2's, re-activates v1's — again no
    // overlapping active demand for the asset.
    (new ActivateVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());

    expect(Demand::query()->whereIn('source_id', $v2Items)->where('is_active', true)->count())->toBe(0)
        ->and(Demand::query()->whereIn('source_id', $v1Items)->where('is_active', true)->count())->toBeGreaterThan(0);
});
