<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Opportunity Versions tab (M8-5) — quote-versioning UI
|--------------------------------------------------------------------------
|
| The tab renders the opportunity's quote versions through the shared
| <x-signals.version-tree> component (revisions + alternatives, the active version
| marked), and drives activate / create / diff / accept / decline / send / rename /
| delete through the SAME version action classes the API uses (each authorises
| internally). It needs a LIVE Verbs Quotation opportunity (factory rows carry a
| synthetic state_id with no event stream and cannot fire version events), so the
| helpers build one through the real actions.
|
| TESTS ARE WRITTEN, NOT RUN (M8 cadence) — the full suite runs once at the M8-end
| gate.
|
*/

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * Build a live Quotation opportunity with one manual line, via the event pipeline.
 */
function quotationForVersionsTab(User $actor): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Versions tab slice',
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

        return $opportunity->refresh();
    } finally {
        Auth::logout();
    }
}

/**
 * Create a version on the opportunity via the action (returns the model).
 *
 * @param  array<string, mixed>  $data
 */
function makeTabVersion(User $actor, Opportunity $opportunity, array $data = []): OpportunityVersion
{
    Auth::login($actor);

    try {
        $result = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from($data));

        return OpportunityVersion::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

it('renders the versions tab for a user with opportunities.view', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    makeTabVersion($this->owner, $opportunity);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->assertOk()
        ->assertSee('Quote Versions');
});

it('forbids the versions tab for a user without opportunities.view', function () {
    $opportunity = quotationForVersionsTab($this->owner);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access');
    $this->actingAs($viewer);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity])->assertForbidden();
});

it('shows the empty state when the opportunity has no versions', function () {
    $opportunity = quotationForVersionsTab($this->owner);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity])
        ->assertSee('No versions yet');
});

it('renders the version tree marking revisions, alternatives and the active version', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    $revision = makeTabVersion($this->owner, $opportunity, ['version_type' => VersionType::Revision->value, 'label' => 'Base quote']);
    $alternative = makeTabVersion($this->owner, $opportunity, ['version_type' => VersionType::Alternative->value, 'label' => 'Budget option']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->assertSeeHtml('version-'.$revision->id)
        ->assertSeeHtml('version-'.$alternative->id)
        ->assertSee('Revision')
        ->assertSee('Alternative')
        ->assertSee('Base quote')
        ->assertSee('Budget option')
        // The most-recently created version is promoted to active.
        ->assertSee('ACTIVE');
});

it('creates a revision via the tab', function () {
    $opportunity = quotationForVersionsTab($this->owner);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity])
        ->set('createType', VersionType::Revision->value)
        ->set('createLabel', 'First revision')
        ->call('createVersion');

    $version = OpportunityVersion::query()->where('opportunity_id', $opportunity->id)->first();
    expect($version)->not->toBeNull()
        ->and($version->version_type)->toBe(VersionType::Revision)
        ->and($version->label)->toBe('First revision');
});

it('creates an alternative via the tab', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    makeTabVersion($this->owner, $opportunity);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->set('createType', VersionType::Alternative->value)
        ->set('createLabel', 'Alternative A')
        ->call('createVersion');

    expect(OpportunityVersion::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('version_type', VersionType::Alternative->value)
        ->where('label', 'Alternative A')
        ->exists())->toBeTrue();
});

it('switches the active version via the tab', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    $first = makeTabVersion($this->owner, $opportunity);
    $second = makeTabVersion($this->owner, $opportunity->refresh());

    // The second is active after creation; activating the first must flip it back.
    expect($opportunity->refresh()->active_version_id)->toBe($second->id);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->call('activate', $first->id);

    expect($opportunity->refresh()->active_version_id)->toBe($first->id);
});

it('renames a version label via the tab', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    $version = makeTabVersion($this->owner, $opportunity, ['label' => 'Old name']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->call('promptRename', $version->id)
        ->set('labelDraft', 'New name')
        ->call('submitRename');

    expect($version->refresh()->label)->toBe('New name');
});

it('accepts a version via the tab', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    $version = makeTabVersion($this->owner, $opportunity);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->call('accept', $version->id);

    expect($version->refresh()->status)->toBe(VersionStatus::Accepted);
});

it('declines a version with a reason via the tab', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    $version = makeTabVersion($this->owner, $opportunity);

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->call('promptDecline', $version->id)
        ->set('declineReason', 'Customer chose another supplier')
        ->call('submitDecline');

    $version->refresh();
    expect($version->status)->toBe(VersionStatus::Declined)
        ->and($version->decline_reason)->toBe('Customer chose another supplier');
});

it('renders the diff between two selected versions', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    $first = makeTabVersion($this->owner, $opportunity);

    // The second version adds a line so the diff has a visible change.
    $second = makeTabVersion($this->owner, $opportunity->refresh());
    Auth::login($this->owner);
    try {
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => 'Extra cabling',
            'quantity' => '1',
            'unit_price' => 2500,
            'version_id' => $second->id,
        ]));
    } finally {
        Auth::logout();
    }

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->set('diffFromId', $first->id)
        ->set('diffToId', $second->id)
        ->assertSee('Net')
        ->assertSee('Extra cabling');
});

it('only offers legal lifecycle actions for a draft, sole, active version', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    makeTabVersion($this->owner, $opportunity);

    $this->actingAs($this->owner);

    // A draft version may be sent / accepted / declined / renamed, but the sole,
    // active version is never offered Make active or Delete (both illegal here).
    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->assertSee('Send')
        ->assertSee('Accept')
        ->assertSee('Decline')
        ->assertSee('Rename')
        ->assertDontSee('Make active')
        ->assertDontSee('Delete');
});

it('offers Make active and Delete only for a non-active version', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    makeTabVersion($this->owner, $opportunity);
    // A second version makes the first non-active and lifts the one-version floor,
    // so the now-inactive version becomes activatable and deletable.
    makeTabVersion($this->owner, $opportunity->refresh());

    $this->actingAs($this->owner);

    Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()])
        ->assertSee('Make active')
        ->assertSee('Delete');
});

it('does not offer mutating actions to a read-only viewer', function () {
    $opportunity = quotationForVersionsTab($this->owner);
    makeTabVersion($this->owner, $opportunity);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    $component = Volt::test('opportunities.versions', ['opportunity' => $opportunity->refresh()]);
    $component->assertOk();
    $component->assertSet('canEdit', false);
    $component->assertDontSee('New revision');
});
