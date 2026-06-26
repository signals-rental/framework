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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function createQuotationWithPricedLine(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Version coverage',
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    return $opportunity->refresh();
}

it('creates the first version with version_number 1 and mirrored totals', function () {
    $opportunity = createQuotationWithPricedLine();

    $result = (new CreateVersion)($opportunity, CreateVersionData::from(['label' => 'Initial quote']));

    $version = OpportunityVersion::query()->whereKey($result->id)->with('items')->firstOrFail();

    expect($result->version_number)->toBe(1)
        ->and($result->label)->toBe('Initial quote')
        ->and($result->is_active)->toBeTrue()
        ->and($version->items)->toHaveCount(1)
        ->and($version->items->first()->total)->toBe($opportunity->refresh()->charge_total)
        ->and($opportunity->active_version_id)->toBe($version->id);
});

it('increments version_number and supersedes the prior active revision', function () {
    $opportunity = createQuotationWithPricedLine();

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'Revision 2']));

    expect($v2->version_number)->toBe(2)
        ->and($v2->parent_version_id)->toBe($v1->id)
        ->and($v2->is_active)->toBeTrue()
        ->and(OpportunityVersion::find($v1->id)?->superseded_by_version_id)->toBe($v2->id)
        ->and($opportunity->refresh()->active_version_id)->toBe($v2->id);
});

it('rejects a source_version_id that does not belong to the opportunity', function () {
    $opportunity = createQuotationWithPricedLine();
    $other = createQuotationWithPricedLine();
    $foreignVersion = (new CreateVersion)($other->refresh(), CreateVersionData::from([]));

    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([
        'source_version_id' => $foreignVersion->id,
    ]));
})->throws(ValidationException::class);

it('requires opportunities.edit permission', function () {
    $this->actingAs(User::factory()->create());
    $opportunity = createQuotationWithPricedLine();

    (new CreateVersion)($opportunity, CreateVersionData::from([]));
})->throws(AuthorizationException::class);
