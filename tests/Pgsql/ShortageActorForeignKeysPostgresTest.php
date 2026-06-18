<?php

use App\Models\ShortageAcknowledgement;
use App\Models\ShortageResolution;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL shortage-actor foreign-key lane
|--------------------------------------------------------------------------
|
| Regression guard for the actor foreign keys on the shortage tables. The
| resolver/confirmer (`shortage_resolutions.resolved_by` / `.confirmed_by`) and
| the acknowledging user (`shortage_acknowledgements.user_id`) are application
| USERS (auth()->id() is a users.id), NOT members. The original migrations
| constrained these columns to `members`, which SQLite silently accepted (it does
| not enforce foreign keys by default in the test suite) but PostgreSQL rejects —
| inserting a real users.id would FK-violate against `members`.
|
| This lane proves the columns now target `users`: inserting rows with a genuine
| users.id persists on real Postgres (where FKs ARE enforced) and the actor
| relations resolve back to that user. It would fail on Postgres if the FK still
| pointed at `members`. Skips when Postgres is unreachable.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
});

it('accepts a real users.id for shortage_resolutions resolved_by/confirmed_by', function () {
    // The factory definition carries no actor fields, so pass them explicitly.
    // On Postgres this insert only succeeds if resolved_by/confirmed_by target
    // users.id — it would FK-violate against members (the bug being regressed).
    $resolution = ShortageResolution::factory()->create([
        'resolved_by' => $this->user->id,
        'confirmed_by' => $this->user->id,
    ]);

    $resolution->refresh();

    expect($resolution->resolved_by)->toBe($this->user->id)
        ->and($resolution->confirmed_by)->toBe($this->user->id)
        ->and($resolution->fresh())->not->toBeNull();

    expect($resolution->resolver->is($this->user))->toBeTrue()
        ->and($resolution->confirmer->is($this->user))->toBeTrue();
});

it('accepts a real users.id for shortage_acknowledgements user_id', function () {
    // The factory provides opportunity_id, acknowledged_at, policy_at_time and
    // shortages_snapshot; we override user_id with a real users.id. On Postgres
    // this only succeeds if user_id targets users.id, not members.
    $acknowledgement = ShortageAcknowledgement::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $acknowledgement->refresh();

    expect($acknowledgement->user_id)->toBe($this->user->id)
        ->and($acknowledgement->fresh())->not->toBeNull();

    expect($acknowledgement->user->is($this->user))->toBeTrue();
});
