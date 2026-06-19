<?php

use App\Actions\Opportunities\ActivateVersion;
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
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * Build a Quotation opportunity with one manual line, via the event pipeline.
 */
function quotationViaEvent(User $actor): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'API Versions', 'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from(['name' => 'Line', 'quantity' => '2', 'unit_price' => 5000]));
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
function makeVersion(User $actor, Opportunity $opportunity, array $data = []): OpportunityVersion
{
    Auth::login($actor);

    try {
        $result = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from($data));

        return OpportunityVersion::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

function vReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

function vWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

it('lists an opportunity\'s versions', function () {
    $opportunity = quotationViaEvent($this->owner);
    makeVersion($this->owner, $opportunity);
    makeVersion($this->owner, $opportunity);

    $token = vReadToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/versions")
        ->assertOk()
        ->assertJsonStructure(['versions' => ['*' => ['id', 'version_number', 'version_type', 'status', 'is_active', 'charge_total']], 'meta'])
        ->assertJsonPath('meta.total', 2);
});

it('creates a version via the API', function () {
    $opportunity = quotationViaEvent($this->owner);
    $token = vWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions", ['label' => 'V1'])
        ->assertCreated()
        ->assertJsonPath('version.version_number', 1)
        ->assertJsonPath('version.is_active', true)
        ->assertJsonPath('version.label', 'V1');
});

it('shows a version with its items when requested', function () {
    $opportunity = quotationViaEvent($this->owner);
    $version = makeVersion($this->owner, $opportunity);
    $token = vReadToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/versions/{$version->id}?include=items")
        ->assertOk()
        ->assertJsonPath('version.id', $version->id)
        ->assertJsonStructure(['version' => ['items']]);
});

it('activates a version via the API', function () {
    $opportunity = quotationViaEvent($this->owner);
    $v1 = makeVersion($this->owner, $opportunity);
    $v2 = makeVersion($this->owner, $opportunity, ['version_type' => VersionType::Alternative->value]);
    $token = vWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions/{$v1->id}/activate")
        ->assertOk()
        ->assertJsonPath('version.is_active', true);

    expect(Opportunity::query()->whereKey($opportunity->id)->firstOrFail()->active_version_id)->toBe($v1->id);
});

it('sends, accepts a version via the API', function () {
    $opportunity = quotationViaEvent($this->owner);
    $version = makeVersion($this->owner, $opportunity);
    $token = vWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions/{$version->id}/send")
        ->assertOk()
        ->assertJsonPath('version.status', VersionStatus::Sent->value);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions/{$version->id}/accept")
        ->assertOk()
        ->assertJsonPath('version.status', VersionStatus::Accepted->value);
});

it('diffs two versions via the API', function () {
    $opportunity = quotationViaEvent($this->owner);
    $v1 = makeVersion($this->owner, $opportunity);
    $v2 = makeVersion($this->owner, $opportunity);
    $token = vReadToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/versions/{$v1->id}/diff/{$v2->id}")
        ->assertOk()
        ->assertJsonStructure(['diff' => ['source_version_id', 'target_version_id', 'added', 'removed', 'changed', 'net_change']]);
});

it('deletes a non-active version via the API', function () {
    $opportunity = quotationViaEvent($this->owner);
    $v1 = makeVersion($this->owner, $opportunity);
    $v2 = makeVersion($this->owner, $opportunity, ['version_type' => VersionType::Alternative->value]);
    // Activate v1 so v2 is deletable.
    Auth::login($this->owner);
    (new ActivateVersion)($v1->refresh());
    Auth::logout();

    $token = vWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/opportunities/{$opportunity->id}/versions/{$v2->id}")
        ->assertNoContent();

    expect(OpportunityVersion::query()->whereKey($v2->id)->exists())->toBeFalse();
});

it('declines a version with a reason via the API', function () {
    $opportunity = quotationViaEvent($this->owner);
    $version = makeVersion($this->owner, $opportunity, ['version_type' => VersionType::Alternative->value]);
    $token = vWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions/{$version->id}/decline", [
            'reason' => 'Budget exceeded',
        ])
        ->assertOk()
        ->assertJsonPath('version.status', VersionStatus::Declined->value)
        ->assertJsonPath('version.decline_reason', 'Budget exceeded');

    expect(OpportunityVersion::query()->whereKey($version->id)->firstOrFail()->decline_reason)
        ->toBe('Budget exceeded');
});

it('404s a version that belongs to another opportunity', function () {
    $opportunityA = quotationViaEvent($this->owner);
    $opportunityB = quotationViaEvent($this->owner);
    $versionB = makeVersion($this->owner, $opportunityB);
    $token = vReadToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunityA->id}/versions/{$versionB->id}")
        ->assertNotFound();
});

it('rejects a creation that violates the version cap with a 422', function () {
    $opportunity = quotationViaEvent($this->owner);
    settings()->set('opportunities.max_versions', 1);
    makeVersion($this->owner, $opportunity);

    $token = vWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions", [])
        ->assertStatus(422);
});

it('requires the write ability to create a version', function () {
    $opportunity = quotationViaEvent($this->owner);
    $token = vReadToken($this->owner); // read-only token

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/opportunities/{$opportunity->id}/versions", [])
        ->assertForbidden();
});
