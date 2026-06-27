<?php

use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\ShortageAcknowledgement;
use App\Models\ShortageWaitlistMonitor;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;

describe('ContainerItem relations', function () {
    it('resolves serialisedItem and product belongsTo relations', function () {
        $stockLevel = StockLevel::factory()->serialised()->create();
        $product = Product::factory()->serialised()->create();

        $item = ContainerItem::factory()->create([
            'serialised_item_id' => $stockLevel->id,
            'product_id' => $product->id,
        ]);

        expect($item->serialisedItem->id)->toBe($stockLevel->id);
        expect($item->product->id)->toBe($product->id);
    });
});

describe('Demand::source morph relation', function () {
    it('resolves the polymorphic source', function () {
        $stockLevel = StockLevel::factory()->create();
        $demand = Demand::factory()->create([
            'source_type' => StockLevel::class,
            'source_id' => $stockLevel->id,
        ]);

        $source = $demand->source;
        expect($source)->toBeInstanceOf(StockLevel::class);
        expect($source instanceof StockLevel ? $source->id : null)->toBe($stockLevel->id);
    });
});

describe('SerialisedComponent relations', function () {
    it('resolves the kit parent product and component product', function () {
        $parent = Product::factory()->create();
        $component = Product::factory()->create();
        $row = SerialisedComponent::factory()->create([
            'product_id' => $parent->id,
            'component_product_id' => $component->id,
        ]);

        expect($row->product->id)->toBe($parent->id);
        expect($row->componentProduct->id)->toBe($component->id);
    });
});

describe('ShortageAcknowledgement relations', function () {
    it('resolves the opportunity and acknowledging user', function () {
        $opportunity = Opportunity::factory()->create();
        $user = User::factory()->create();
        $ack = ShortageAcknowledgement::factory()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
        ]);

        expect($ack->opportunity->id)->toBe($opportunity->id);
        expect($ack->user->id)->toBe($user->id);
    });
});

describe('ShortageWaitlistMonitor relations', function () {
    it('resolves product and store belongsTo relations', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $monitor = ShortageWaitlistMonitor::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);

        expect($monitor->product->id)->toBe($product->id);
        expect($monitor->store->id)->toBe($store->id);
    });
});
