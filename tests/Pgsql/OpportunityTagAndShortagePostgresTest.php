<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\OpportunityStatus;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Api\RansackFilter;
use App\Services\Api\WebhookService;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Shortages\ShortageDetector;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL tag_list (JSONB) filter + has_shortage maintenance lane
|--------------------------------------------------------------------------
|
| Proves, against real Postgres:
|   - the RansackFilter JSONB branch (whereJsonContains) actually filters a
|     jsonb `tag_list` array, where the old scalar ilike path would error;
|   - RecalculateAvailabilityJob maintains the denormalised
|     `opportunities.has_shortage` flag (set on shortage, cleared on resolve).
| Skips when Postgres is unreachable.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('filters opportunities by a JSONB tag_list membership against Postgres', function () {
    $vip = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'VIP', 'tag_list' => ['vip', 'rush'],
    ]));
    (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Plain', 'tag_list' => ['standard'],
    ]));

    $filtered = (new RansackFilter)->apply(
        Opportunity::query(),
        ['tag_list_cont' => 'vip'],
        ['tag_list'],
    )->get();

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->id)->toBe($vip->id);
});

it('maintains opportunities.has_shortage through the recalc job', function () {
    // Fake the queue so the demand/stock observers only ENQUEUE the recalc job
    // rather than running it synchronously — the explicit handle() calls below are
    // then the sole driver of the denormalised flag, which is what this test
    // asserts ("through the recalc job"). Without this the observer would run the
    // recalc inline on every demand/stock write and the flag would already be set
    // before the explicit job runs, defeating the false → true → false sequence.
    Queue::fake();

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    $stock = StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 1,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Shortage job',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    // Request more than is held → a genuine shortage.
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '5',
    ]));

    // Move the opportunity to a status whose demand phase is ACTIVE
    // (Quotation → Reserved = DemandPhase::Committed). A Draft opportunity's
    // demand is the Draft phase (inactive) and consumes no availability, so the
    // recalc job's active-demand enumeration would correctly skip it and the
    // flag would never flip — the shortage only "exists" once the booking
    // actually claims stock. CreateOpportunity leaves the deal in Draft, so the
    // genuine shortage materialises only after it reserves.
    (new ConvertToQuotation)($opportunity->fresh());
    (new ChangeOpportunityStatus)($opportunity->fresh(), OpportunityStatus::QuotationReserved);

    expect($opportunity->fresh()->has_shortage)->toBeFalse();

    // The recalc job is what maintains the denormalised flag.
    (new RecalculateAvailabilityJob($product->id, $store->id))->handle(
        app(RecalculationPipeline::class),
        app(WebhookService::class),
        app(ShortageDetector::class),
    );

    expect($opportunity->fresh()->has_shortage)->toBeTrue();

    // Relieve the shortage (plenty of stock) and re-run → flag clears.
    $stock->forceFill(['quantity_held' => 50])->saveQuietly();

    (new RecalculateAvailabilityJob($product->id, $store->id))->handle(
        app(RecalculationPipeline::class),
        app(WebhookService::class),
        app(ShortageDetector::class),
    );

    expect($opportunity->fresh()->has_shortage)->toBeFalse();
});
