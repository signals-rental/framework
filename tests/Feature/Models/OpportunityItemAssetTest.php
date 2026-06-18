<?php

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;

it('belongs to an opportunity item', function () {
    $item = OpportunityItem::factory()->create();
    $asset = OpportunityItemAsset::factory()->for($item, 'item')->create();

    expect($asset->item)->toBeInstanceOf(OpportunityItem::class)
        ->and($asset->item->id)->toBe($item->id);
});

it('belongs to a stock level', function () {
    $asset = OpportunityItemAsset::factory()->create();

    expect($asset->stockLevel)->toBeInstanceOf(StockLevel::class);
});

it('optionally belongs to a container stock level', function () {
    $container = StockLevel::factory()->create();
    $asset = OpportunityItemAsset::factory()->create(['container_stock_level_id' => $container->id]);

    expect($asset->container)->toBeInstanceOf(StockLevel::class)
        ->and($asset->container->id)->toBe($container->id);

    $bare = OpportunityItemAsset::factory()->create(['container_stock_level_id' => null]);
    expect($bare->container)->toBeNull();
});

it('uses an application-assigned non-incrementing integer primary key', function () {
    $asset = new OpportunityItemAsset;

    expect($asset->getIncrementing())->toBeFalse()
        ->and($asset->getKeyType())->toBe('int');

    $created = OpportunityItemAsset::factory()->create(['id' => 9191]);
    expect($created->id)->toBe(9191);
    $this->assertDatabaseHas('opportunity_item_assets', ['id' => 9191]);
});

it('casts status and condition_on_return to enums', function () {
    $asset = OpportunityItemAsset::factory()->create([
        'status' => AssetAssignmentStatus::CheckedIn->value,
        'condition_on_return' => AssetCondition::Damaged->value,
    ]);

    $fresh = $asset->fresh();

    expect($fresh->status)->toBe(AssetAssignmentStatus::CheckedIn)
        ->and($fresh->condition_on_return)->toBe(AssetCondition::Damaged);
});

it('leaves condition_on_return null until assessed', function () {
    $asset = OpportunityItemAsset::factory()->create(['condition_on_return' => null]);

    expect($asset->fresh()->condition_on_return)->toBeNull();
});

it('nulls the stock_level_id when the stock level is deleted', function () {
    $stockLevel = StockLevel::factory()->create();
    $asset = OpportunityItemAsset::factory()->create(['stock_level_id' => $stockLevel->id]);

    $stockLevel->delete();

    expect($asset->fresh()->stock_level_id)->toBeNull();
    $this->assertDatabaseHas('opportunity_item_assets', ['id' => $asset->id]);
});
