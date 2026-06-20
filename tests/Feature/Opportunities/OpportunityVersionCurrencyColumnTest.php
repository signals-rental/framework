<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Bug #5 — opportunity_versions.currency_code migration drift
|--------------------------------------------------------------------------
|
| The M1–M5 audit (R3) added `currency_code` + `exchange_rate` to
| `opportunity_versions` by EDITING the already-run create migration rather than
| adding a new one. Any incrementally-migrated database (dev, prod) was therefore
| missing the columns, so VersionCreated::handle()'s insert threw:
|
|   SQLSTATE[42703]: column "currency_code" does not exist
|
| migrate:fresh (the test DB) had the columns, so the suite stayed green and missed
| it. The fix is a guarded corrective migration
| (2026_06_20_130000_add_currency_to_opportunity_versions_table) that adds the
| columns only when absent — a no-op on fresh, an additive fix on drifted DBs.
|
| These tests guard: (a) both columns survive migration, (b) the corrective
| migration is idempotent (re-running its up() is a safe no-op), and (c) the
| CreateVersion insert path persists the opportunity's currency on the version row.
|
*/

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('has the currency_code and exchange_rate columns on opportunity_versions after migration', function () {
    // If anyone drops the columns from BOTH the create migration and the corrective
    // migration, the version insert path breaks again — this fails first.
    expect(Schema::hasColumn('opportunity_versions', 'currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('opportunity_versions', 'exchange_rate'))->toBeTrue();
});

it('runs the corrective currency migration idempotently (no-op when the columns exist)', function () {
    // The columns are already present after migrate:fresh. Re-running the
    // corrective migration's up() must be a clean no-op (it is hasColumn-guarded),
    // not a duplicate-column error.
    $migration = require database_path(
        'migrations/2026_06_20_130000_add_currency_to_opportunity_versions_table.php'
    );

    expect(fn () => $migration->up())->not->toThrow(Throwable::class);

    // The columns are still present and intact after the re-run.
    expect(Schema::hasColumn('opportunity_versions', 'currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('opportunity_versions', 'exchange_rate'))->toBeTrue();
});

it('persists the opportunity currency_code on a created version row', function () {
    Auth::login($this->owner);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Currency drift guard',
            'starts_at' => '2026-09-01T09:00:00Z',
            'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Stage deck',
            'quantity' => '2',
            'unit_price' => 5000,
        ]));

        (new ConvertToQuotation)($opportunity->refresh());

        // The insert that previously threw "column currency_code does not exist".
        $result = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    } finally {
        Auth::logout();
    }

    $version = OpportunityVersion::query()->whereKey($result->id)->firstOrFail();

    expect($version->currency_code)->toBe($opportunity->refresh()->currency_code);
});
