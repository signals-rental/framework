<?php

use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * R-D master D2 — opportunity_items.version_id is now FK-constrained to
 * opportunity_versions(id) with nullOnDelete (the column was created
 * unconstrained in M3, before the versions table existed).
 *
 * This lane only proves the SQLite-safe behaviour (the suite default): the
 * migration applies cleanly and a valid version_id round-trips. FK *enforcement*
 * (rejecting a dangling id, nullOnDelete on version delete) is exercised on real
 * Postgres in tests/Pgsql/OpportunityItemVersionForeignKeyPostgresTest.php, since
 * SQLite does not enforce foreign keys inside the suite's wrapping transaction.
 */
it('persists an opportunity_item linked to a real version', function () {
    $version = OpportunityVersion::factory()->create();

    $item = OpportunityItem::factory()->create([
        'version_id' => $version->id,
    ]);

    expect($item->fresh()->version_id)->toBe($version->id);
});

it('allows a null version_id (orders / legacy / non-versioned lines)', function () {
    $item = OpportunityItem::factory()->create([
        'version_id' => null,
    ]);

    expect($item->fresh()->version_id)->toBeNull();
});
