<?php

use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Store;

describe('OpportunityItem store override relations', function () {
    it('resolves dispatchStore and returnStore belongsTo relations', function () {
        $dispatchStore = Store::factory()->create();
        $returnStore = Store::factory()->create();
        $item = OpportunityItem::factory()->create([
            'dispatch_store_id' => $dispatchStore->id,
            'return_store_id' => $returnStore->id,
        ]);

        expect($item->dispatchStore->id)->toBe($dispatchStore->id);
        expect($item->returnStore->id)->toBe($returnStore->id);
    });
});

describe('OpportunityVersion::parentVersion relation', function () {
    it('resolves the superseded parent version', function () {
        $first = OpportunityVersion::factory()->create();
        $revision = OpportunityVersion::factory()->create([
            'opportunity_id' => $first->opportunity_id,
            'parent_version_id' => $first->id,
        ]);

        expect($revision->parentVersion->id)->toBe($first->id);
    });
});
