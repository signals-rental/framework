<?php

use App\Events\Availability\AvailabilityChanged;
use App\Events\Availability\OpportunityAvailabilityChanged;
use App\Jobs\RebuildSnapshotsJob;
use App\Jobs\RecalculateAvailabilityJob;
use App\Services\Api\WebhookService;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Availability\RecalculationResult;
use App\Services\Shortages\ShortageDetector;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

it('serialises RebuildSnapshotsJob runs with a WithoutOverlapping middleware keyed on product/store', function () {
    $job = new RebuildSnapshotsJob(42, 9);

    $middleware = collect($job->middleware());

    $overlapping = $middleware->first(fn (object $m): bool => $m instanceof WithoutOverlapping);

    expect($middleware)->toHaveCount(1)
        ->and($overlapping)->not->toBeNull();

    // The lock key embeds product:store so distinct pairs do not block each other.
    $key = (new ReflectionProperty(WithoutOverlapping::class, 'key'))->getValue($overlapping);
    expect($key)->toBe('42:9');
});

it('skips per-opportunity broadcasts when the recalc window is unknown (null from/to) but still broadcasts product/store', function () {
    Event::fake([AvailabilityChanged::class, OpportunityAvailabilityChanged::class]);

    // A pipeline that reports a real recompute (slots > 0) but with an UNKNOWN
    // window (null from/to). handle() must still emit the product/store
    // AvailabilityChanged, but broadcastToOpportunities() short-circuits on the
    // null window so no per-opportunity broadcast fires.
    $pipeline = Mockery::mock(RecalculationPipeline::class);
    $pipeline->shouldReceive('fullHorizon')->andReturn([
        Carbon::parse('2026-06-18T00:00:00Z'),
        Carbon::parse('2026-06-25T00:00:00Z'),
    ]);
    $pipeline->shouldReceive('recalculate')->andReturn(
        new RecalculationResult(101, 5, null, null, 3, false),
    );

    (new RecalculateAvailabilityJob(101, 5))->handle(
        $pipeline,
        app(WebhookService::class),
        app(ShortageDetector::class),
    );

    Event::assertDispatched(AvailabilityChanged::class, function (AvailabilityChanged $event): bool {
        return $event->productId === 101 && $event->storeId === 5 && $event->slots === 3;
    });

    // The null window suppressed every opportunity-scoped broadcast.
    Event::assertNotDispatched(OpportunityAvailabilityChanged::class);
});
