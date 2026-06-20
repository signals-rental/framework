<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\RestoreOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| RestoreOpportunity action (M8-1)
|--------------------------------------------------------------------------
|
| RestoreOpportunity reverses a soft-delete via the event-sourced
| OpportunityRestored event (mirroring DeleteOpportunity / OpportunityDeleted).
| It reuses the opportunities.delete permission — the same authority that
| archived the record un-archives it.
|
| Opportunities created through the real action pipeline have a genuine Verbs
| state, so the restore event can resolve its state and dual-write the
| projection. (Factory rows carry a synthetic state_id with no event stream and
| are unsafe for lifecycle events.)
|
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Create an opportunity through the real event pipeline as the given actor.
 */
function createRestorableOpportunity(User $actor, int $storeId): Opportunity
{
    Auth::login($actor);

    try {
        $result = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Restorable opportunity',
            'store_id' => $storeId,
        ]));

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

it('restores a soft-deleted opportunity and records an audit row', function () {
    $opportunity = createRestorableOpportunity($this->actor, $this->store->id);

    // createRestorableOpportunity logs out in its finally block; re-authenticate
    // the owner for the action-layer Gate::authorize() checks.
    $this->actingAs($this->actor);

    (new DeleteOpportunity)($opportunity);
    expect(Opportunity::withTrashed()->find($opportunity->id)->trashed())->toBeTrue();

    (new RestoreOpportunity)(Opportunity::withTrashed()->findOrFail($opportunity->id));

    $restored = Opportunity::find($opportunity->id);
    expect($restored)->not->toBeNull();
    expect($restored->trashed())->toBeFalse();

    $this->assertDatabaseHas('action_logs', [
        'action' => 'opportunity.restored',
        'auditable_type' => Opportunity::class,
        'auditable_id' => $opportunity->id,
    ]);
});

it('is a safe no-op when restoring a non-trashed opportunity', function () {
    $opportunity = createRestorableOpportunity($this->actor, $this->store->id);

    $this->actingAs($this->actor);

    // Not deleted — restore must early-return without touching the row or
    // writing a spurious audit entry.
    (new RestoreOpportunity)($opportunity);

    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();

    $this->assertDatabaseMissing('action_logs', [
        'action' => 'opportunity.restored',
        'auditable_id' => $opportunity->id,
    ]);
});

it('enforces the opportunities.delete permission', function () {
    $opportunity = createRestorableOpportunity($this->actor, $this->store->id);

    Auth::login($this->actor);
    (new DeleteOpportunity)($opportunity);
    Auth::logout();

    // A user lacking opportunities.delete cannot restore.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['opportunities.access', 'opportunities.view']);
    $this->actingAs($viewer);

    expect(fn () => (new RestoreOpportunity)(Opportunity::withTrashed()->findOrFail($opportunity->id)))
        ->toThrow(AuthorizationException::class);

    // Still archived — the failed authorisation did not restore it.
    expect(Opportunity::withTrashed()->find($opportunity->id)->trashed())->toBeTrue();
});
