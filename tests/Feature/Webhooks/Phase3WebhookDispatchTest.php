<?php

use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\StockMethod;
use App\Jobs\DeliverWebhook;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Shortages\ShortageEventRecorder;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Subscribe a webhook to a wildcard so every Phase-3 event reaches it; the
 * factory writes an active row owned by the acting user.
 */
function subscribeAllEvents(User $owner): Webhook
{
    return Webhook::factory()->create([
        'user_id' => $owner->id,
        'events' => ['*'],
        'is_active' => true,
    ]);
}

it('dispatches a webhook once for an opportunity lifecycle transition', function () {
    Queue::fake();
    subscribeAllEvents($this->actor);

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Webhook lifecycle']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    // The quoted transition rides the audit bridge → central webhook listener,
    // and must enqueue exactly one delivery for that event (one fire, no replay
    // double-up). The closure counts the matching pushes, so passing it asserts
    // BOTH that the quoted delivery fired and that it fired exactly once.
    $quotedPushes = 0;
    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) use (&$quotedPushes): bool {
        if ($job->event === 'opportunity.quoted') {
            $quotedPushes++;
        }

        return $job->event === 'opportunity.quoted';
    });

    expect($quotedPushes)->toBe(1);

    // The create event also fired, with the full opportunity DTO payload under
    // the `opportunity` key (entity-DTO shape, not the lean id envelope).
    Queue::assertPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'opportunity.created'
            && array_key_exists('opportunity', $job->payload)
            && ($job->payload['opportunity']['id'] ?? null) === $created->id,
    );
});

it('does NOT dispatch opportunity webhooks during a Verbs replay', function () {
    subscribeAllEvents($this->actor);

    // Fire the lifecycle for real first (outside any fake), then start faking and
    // replay: the central listener is Verbs::isReplaying()-guarded, so the
    // re-dispatched audit events must enqueue NOTHING.
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Replay safe webhook']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    Queue::fake();

    Verbs::replay();

    Queue::assertNothingPushed();
});

it('still writes audit rows on replay even though webhooks are skipped', function () {
    subscribeAllEvents($this->actor);

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Audit survives replay']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $before = ActionLog::query()->where('auditable_id', $created->id)->count();
    expect($before)->toBe(2);

    // Replay re-runs handle() (and re-dispatches the audit bridge) but
    // firstOrCreate on verb_event_id keeps the audit count stable — proving the
    // webhook replay-skip did not disturb audit semantics.
    Queue::fake();
    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $created->id)->count())->toBe($before);
});

it('dispatches a shortage.cleared webhook when a shortage clears', function () {
    Queue::fake();
    subscribeAllEvents($this->actor);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();

    $shortage = Shortage::make(
        opportunityItemId: 1,
        opportunityId: 1,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 4,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );

    app(ShortageEventRecorder::class)->cleared($shortage, 'stock_returned');

    Queue::assertPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'shortage.cleared'
            && ($job->payload['product_id'] ?? null) === $product->id,
    );
});

it('does NOT dispatch a shortage webhook during a Verbs replay', function () {
    subscribeAllEvents($this->actor);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();

    $shortage = Shortage::make(
        opportunityItemId: 1,
        opportunityId: 1,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 4,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );

    Queue::fake();

    Verbs::replay(beforeEach: function () use ($shortage): void {
        app(ShortageEventRecorder::class)->cleared($shortage, 'stock_returned');
    });

    Queue::assertNotPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'shortage.cleared',
    );
});

it('dispatches an availability.changed webhook after a recompute', function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

    Queue::fake();
    $store = Store::factory()->create(['timezone' => 'UTC']);
    subscribeAllEvents($this->actor);

    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 3,
    ]);

    (new RecalculateAvailabilityJob($product->id, $store->id))->handle(
        app(RecalculationPipeline::class),
    );

    Queue::assertPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'availability.changed'
            && ($job->payload['product_id'] ?? null) === $product->id
            && ($job->payload['store_id'] ?? null) === $store->id,
    );

    Carbon::setTestNow();
});
