<?php

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Enums\VersionStatus;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\User;
use App\Verbs\Events\Opportunities\VersionAccepted;
use App\Verbs\Events\Opportunities\VersionActivated;
use App\Verbs\Events\Opportunities\VersionDeclined;
use App\Verbs\Events\Opportunities\VersionLabelChanged;
use App\Verbs\Events\Opportunities\VersionSent;
use App\Verbs\Events\Opportunities\VersionSuperseded;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Verbs;

/**
 * Covers the defensive `if ($version === null) { return; }` guard in the version
 * events' handle() (keyed on the `opportunity_versions` projection row). The guard
 * fires when the version projection row was hard-deleted out from under a later
 * event in the same Verbs stream.
 *
 * These events' validate() reads the Verbs state plus the parent `opportunities`
 * row — NOT the version projection row — so deleting the version row alone leaves
 * validation intact, lets the event fire, and exercises the handle() guard.
 *
 * (VersionDeleted is excluded: its validate() reads the version projection row
 * directly and asserts version_count > 1, so the delete-then-fire mechanism cannot
 * keep its validation passing — see the report's genuinely-unreachable notes.)
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Build a Quotation with one active draft version.
 */
function versionGuardVersion(): OpportunityVersion
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Version guard',
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack', 'quantity' => '1', 'unit_price' => 5000,
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    $version = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    return OpportunityVersion::query()->whereKey($version->id)->firstOrFail();
}

it('single-state version handle() guards a missing projection row', function (callable $fire) {
    // Unwrap the lazy dataset closure once to get the real fire callable.
    $fire = $fire();

    $version = versionGuardVersion();
    $stateId = $version->state_id;
    OpportunityVersion::query()->whereKey($version->id)->delete();

    DB::transaction(function () use ($stateId, $fire) {
        $fire($stateId);
        Verbs::commit();
    });

    expect(OpportunityVersion::query()->whereKey($version->id)->exists())->toBeFalse();
})->with([
    'accepted' => [fn () => fn (int $id) => VersionAccepted::fire(version_id: $id, accepted_by: null, accepted_at: '2026-09-02T10:00:00Z')],
    'declined' => [fn () => fn (int $id) => VersionDeclined::fire(version_id: $id, reason: 'too dear', declined_at: '2026-09-02T10:00:00Z')],
    'sent' => [fn () => fn (int $id) => VersionSent::fire(version_id: $id, sent_to: null, sent_via: 'email', sent_at: '2026-09-02T10:00:00Z')],
    'relabelled' => [fn () => fn (int $id) => VersionLabelChanged::fire(version_id: $id, label: 'Renamed')],
]);

it('version superseded handle() guards a missing version projection row', function () {
    $version = versionGuardVersion();
    $stateId = $version->state_id;
    OpportunityVersion::query()->whereKey($version->id)->delete();

    DB::transaction(function () use ($stateId) {
        VersionSuperseded::fire(version_id: $stateId, superseded_by_version_id: null);
        Verbs::commit();
    });

    expect(OpportunityVersion::query()->whereKey($version->id)->exists())->toBeFalse();
});

it('superseding an accepted version preserves the decision and records lineage only', function () {
    $version = versionGuardVersion();
    (new AcceptVersion)($version, null);
    expect($version->refresh()->status)->toBe(VersionStatus::Accepted);

    // A second version supersedes the accepted one: the Accepted decision is
    // preserved (status stays Accepted) but the forward-lineage pointer is recorded.
    $opportunity = $version->opportunity()->firstOrFail();
    $successor = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    DB::transaction(function () use ($version, $successor) {
        VersionSuperseded::fire(
            version_id: $version->state_id,
            superseded_by_version_id: $successor->id,
        );
        Verbs::commit();
    });

    $version->refresh();
    expect($version->status)->toBe(VersionStatus::Accepted)
        ->and($version->superseded_by_version_id)->toBe($successor->id);
});

it('version activated handle() guards a missing version projection row', function () {
    $version = versionGuardVersion();
    $opportunity = $version->opportunity()->firstOrFail();

    $versionStateId = $version->state_id;
    $versionPk = $version->id;
    $opportunityStateId = $opportunity->state_id;

    OpportunityVersion::query()->whereKey($version->id)->delete();

    DB::transaction(function () use ($versionStateId, $versionPk, $opportunityStateId) {
        VersionActivated::fire(
            version_id: $versionStateId,
            version_pk: $versionPk,
            opportunity_id: $opportunityStateId,
        );
        Verbs::commit();
    });

    expect(OpportunityVersion::query()->whereKey($versionPk)->exists())->toBeFalse();
});
