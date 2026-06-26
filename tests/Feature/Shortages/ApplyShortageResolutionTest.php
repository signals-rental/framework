<?php

use App\Actions\Shortages\ApplyShortageResolution;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Shortages\ApplyResolutionData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortageResolutionStatus;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\PartialFulfilmentResolver;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\ShortageDetector;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 555001,
        ]);

    $this->opportunity = Opportunity::factory()->create([
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);

    $this->item = OpportunityItem::factory()->for($this->opportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $this->product->id,
        'quantity' => 3,
    ]);

    $this->action = app(ApplyShortageResolution::class);
});

it('requires the shortages.resolve permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(fn () => ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'partial',
    ])))->toThrow(AuthorizationException::class);
});

it('applies the chosen resolver option for an authorised user', function () {
    $this->actingAs(User::factory()->owner()->create());

    $result = ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'partial',
        'option_index' => 0,
    ]));

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(ShortageResolutionStatus::Confirmed)
        ->and($result->resolution->resolver_key)->toBe('partial');

    $this->assertDatabaseHas('shortage_resolution_items', [
        'shortage_resolution_id' => $result->resolution->id,
        'opportunity_item_id' => $this->item->id,
    ]);
});

it('rejects items with no current shortage', function () {
    $this->actingAs(User::factory()->owner()->create());

    Demand::query()->where('source_id', 555001)->delete();

    expect(fn () => ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'partial',
    ])))->toThrow(ValidationException::class, 'no current shortage');
});

it('rejects an unknown resolver key', function () {
    $this->actingAs(User::factory()->owner()->create());

    expect(fn () => ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'not_registered',
    ])))->toThrow(ValidationException::class, 'Unknown shortage resolver');
});

it('rejects a resolver that is not applicable to the shortage', function () {
    $this->actingAs(User::factory()->owner()->create());

    // Waitlist always applies, so use transfer in a single-warehouse store.
    expect(fn () => ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'transfer',
    ])))->toThrow(ValidationException::class, 'not applicable');
});

it('rejects an out-of-range option index', function () {
    $this->actingAs(User::factory()->owner()->create());

    expect(fn () => ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'waitlist',
        'option_index' => 3,
    ])))->toThrow(ValidationException::class, 'no longer available');
});

it('selects a non-default option by index', function () {
    $this->actingAs(User::factory()->owner()->create());

    $partial = app(PartialFulfilmentResolver::class);
    $waitlist = app(WaitlistResolver::class);

    $shortage = app(ShortageDetector::class)->forItem($this->item);

    expect($partial->getOptions($shortage))->toHaveCount(1)
        ->and($waitlist->getOptions($shortage))->toHaveCount(1);

    $result = ($this->action)(ApplyResolutionData::from([
        'opportunity_item_id' => $this->item->id,
        'resolver_key' => 'waitlist',
        'option_index' => 0,
    ]));

    expect($result->status)->toBe(ShortageResolutionStatus::Monitoring)
        ->and($result->resolution->resolver_key)->toBe('waitlist');
});
