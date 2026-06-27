<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function treeBuilderCovOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Tree cov',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('labels an auto-group destination with the Group prefix', function () {
    $opportunity = treeBuilderCovOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Lighting',
        'custom_fields' => [OpportunityEditorTreeService::AUTO_GROUP_KEY_FIELD => 'auto:lighting'],
    ]));

    $destinations = app(OpportunityLineItemsTreeBuilder::class)->destinations($opportunity->fresh());

    expect(collect($destinations)->pluck('label')->all())->toContain('Group · Lighting');
});

it('resolves a product id from a legacy lowercase namespaced itemable type', function () {
    $opportunity = treeBuilderCovOpportunity();
    $product = Product::factory()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Legacy link',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // Rewrite the stored type to a lowercase namespaced form so isProductBacked()
    // is false but the catalogue resolver's str_ends_with('\product') path matches.
    $opportunity->allItems()->where('name', 'Legacy link')->firstOrFail()
        ->forceFill(['itemable_type' => 'app\\models\\product'])->saveQuietly();

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh());
    $row = collect($tree)->firstWhere('name', 'Legacy link');

    expect($row['product_id'])->toBe($product->id);
});

it('returns no product id for a non-product itemable type', function () {
    $opportunity = treeBuilderCovOpportunity();
    $product = Product::factory()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Service link',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // A genuinely non-product itemable type -> resolver falls through to null.
    $opportunity->allItems()->where('name', 'Service link')->firstOrFail()
        ->forceFill(['itemable_type' => 'App\\Models\\Service'])->saveQuietly();

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh());
    $row = collect($tree)->firstWhere('name', 'Service link');

    expect($row['product_id'])->toBeNull();
});
