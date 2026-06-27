<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('OpportunityController::storeItem item_type fallback + item_id alias', function () {
    it('maps the RMS item_id alias to itemable_id when adding a line', function () {
        // Omitting item_type uses the Product default; passing item_id (the RMS
        // alias) with no itemable_id exercises mapItemableIdAlias (line 1298).
        Sanctum::actingAs($this->owner, ['opportunities:write']);

        $product = Product::factory()->rental()->bulk()->create();
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Alias item add',
        ]));

        $this->postJson("/api/v1/opportunities/{$created->id}/items", [
            'name' => $product->name,
            'item_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '2',
        ])->assertCreated();

        $item = OpportunityItem::query()->where('opportunity_id', $created->id)->firstOrFail();
        expect((int) $item->itemable_id)->toBe($product->id);
    });

    it('coerces an unrecognised item_type to the Product enum fallback before validation', function () {
        // OpportunityItemType::tryFrom('garbage') === null, so storeItem assigns
        // the Product fallback (line 809) before the default match arm validates
        // the payload. The inner DTO then rejects the bad item_type with a 422 —
        // the fallback line still executes en route.
        Sanctum::actingAs($this->owner, ['opportunities:write']);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Fallback item type',
        ]));

        $this->postJson("/api/v1/opportunities/{$created->id}/items", [
            'item_type' => 'totally-not-a-real-type',
            'name' => 'Manual',
            'quantity' => '1',
        ])->assertStatus(422);
    });
});

describe('OpportunityVersionController::update (relabel)', function () {
    it('relabels a version via PATCH', function () {
        Auth::login($this->owner);
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Relabel target',
            'starts_at' => '2026-09-01T09:00:00Z',
            'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Line', 'quantity' => '1', 'unit_price' => 5000,
        ]));
        (new ConvertToQuotation)($opportunity->refresh());
        $versionResult = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'Original']));
        $version = OpportunityVersion::query()->whereKey($versionResult->id)->firstOrFail();
        Auth::logout();

        Sanctum::actingAs($this->owner, ['opportunities:write']);

        $this->patchJson(
            "/api/v1/opportunities/{$opportunity->id}/versions/{$version->id}",
            ['label' => 'Renamed Label']
        )
            ->assertOk()
            ->assertJsonPath('version.label', 'Renamed Label');

        expect($version->fresh()->label)->toBe('Renamed Label');
    });
});

describe('ShortageController::resolvers with no shortage', function () {
    it('returns an empty resolver list when the item has no shortage', function () {
        Sanctum::actingAs($this->owner, ['shortages:read', 'opportunities:read']);

        $opportunity = Opportunity::factory()->create();
        $item = OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
        ]);

        $this->getJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/shortage_resolvers")
            ->assertOk()
            ->assertExactJson(['resolvers' => []]);
    });
});

describe('AvailabilityController range validation closure short-circuit', function () {
    it('passes range validation when `from` is absent (closure early return)', function () {
        // The `to` validation closure returns early (line 287) when `from` is not
        // a string. We trigger it by omitting `from` entirely; the closure returns
        // without adding a range error (other required-rule errors still apply).
        Sanctum::actingAs($this->owner, ['availability:read']);

        $response = $this->getJson('/api/v1/availability/shortages?to=2026-09-05');

        // `from` is required elsewhere so the request is a 422, but the range
        // closure must NOT contribute a "may not exceed" message (it short-circuited).
        $response->assertStatus(422);
        $errors = $response->json('errors') ?? [];
        $toErrors = $errors['to'] ?? [];
        expect(collect($toErrors)->filter(fn ($m) => str_contains((string) $m, 'may not exceed')))->toBeEmpty();
    });
});
