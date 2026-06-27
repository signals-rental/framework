<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityLineItemTreeRevision;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Models\VerbStateEvent;

it('returns zero when the opportunity has no items', function () {
    $opportunity = Opportunity::factory()->create();

    expect(app(OpportunityLineItemTreeRevision::class)->current($opportunity->id))->toBe(0);
});

it('resolves the latest state-event id touching the opportunity items', function () {
    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'state_id' => snowflake_id(),
    ]);

    VerbStateEvent::query()->insert([
        'state_id' => $item->state_id,
        'event_id' => 4242,
        'state_type' => 'App\\States\\Opportunities\\OpportunityItemState',
    ]);

    expect(app(OpportunityLineItemTreeRevision::class)->current($opportunity->id))->toBe(4242);
});

it('returns zero when the verb_state_events table is absent', function () {
    $opportunity = Opportunity::factory()->create();
    OpportunityItem::factory()->create(['opportunity_id' => $opportunity->id]);

    // Drop the events table so the revision resolver hits its missing-table guard
    // (line 30) before any VerbStateEvent query is attempted.
    Schema::dropIfExists('verb_state_events');

    expect(app(OpportunityLineItemTreeRevision::class)->current($opportunity->id))->toBe(0);
});
