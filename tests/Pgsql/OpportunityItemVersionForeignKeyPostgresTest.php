<?php

use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use Illuminate\Database\QueryException;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL opportunity_items.version_id foreign-key lane (R-D master D2)
|--------------------------------------------------------------------------
|
| opportunity_items.version_id was created unconstrained in M3 (the
| opportunity_versions table did not yet exist). R-D adds the FK
| (references opportunity_versions(id), nullOnDelete). SQLite silently accepts
| dangling values inside the suite's wrapping transaction, so the enforcement is
| proven here on real Postgres where FKs ARE enforced.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

it('rejects an opportunity_item referencing a non-existent version', function () {
    expect(fn () => OpportunityItem::factory()->create([
        'version_id' => 999999999,
    ]))->toThrow(QueryException::class);
});

it('nulls the version_id when its version is deleted (nullOnDelete)', function () {
    $version = OpportunityVersion::factory()->create();

    $item = OpportunityItem::factory()->create([
        'version_id' => $version->id,
    ]);

    // Hard-delete the version row (no SoftDeletes on OpportunityVersion).
    OpportunityVersion::query()->whereKey($version->id)->delete();

    expect($item->fresh()->version_id)->toBeNull();
});
