<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\OpportunityState;
use App\Enums\ShortagePolicy;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ShortageAcknowledgement;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\ShortageConfirmationGate;
use App\Services\Shortages\ShortageEventRecorder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
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
});

/**
 * A Quotation with a single product line that is short by 2 (5 held, 4 committed
 * elsewhere, line wants 3) at the given store, under the given policy.
 */
function shortQuotation(ShortagePolicy $policy): Opportunity
{
    $store = Store::factory()->shortagePolicy($policy)->create(['timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 888001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Gate test',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));

    (new ConvertToQuotation)($opportunity->fresh());

    return $opportunity->fresh();
}

it('blocks conversion under a Block policy when shortages exist', function () {
    // A non-owner without the ignore permission.
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $opportunity = shortQuotation(ShortagePolicy::Block);

    expect(fn () => (new ConvertToOrder)($opportunity))
        ->toThrow(ValidationException::class);

    // Conversion rolled back: still a quotation.
    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation)
        ->and(ShortageAcknowledgement::query()->count())->toBe(0);
});

it('does not emit shortage.detected telemetry when the gate blocks (would be rolled back)', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $opportunity = shortQuotation(ShortagePolicy::Block);

    // Spy the event recorder AFTER setup (add-time probe emits its own detected
    // event) so we measure only the gate's emission. The gate must never call
    // detected() on the Block path, where the enclosing transaction would roll the
    // write straight back.
    $spy = Mockery::spy(ShortageEventRecorder::class);
    $this->app->instance(ShortageEventRecorder::class, $spy);

    $gate = app(ShortageConfirmationGate::class);

    expect(fn () => $gate->enforceForConfirmation($opportunity))
        ->toThrow(ValidationException::class);

    $spy->shouldNotHaveReceived('detected');
});

it('emits shortage.detected telemetry when the gate proceeds (Warn)', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $opportunity = shortQuotation(ShortagePolicy::Warn);

    $spy = Mockery::spy(ShortageEventRecorder::class);
    $this->app->instance(ShortageEventRecorder::class, $spy);

    $gate = app(ShortageConfirmationGate::class);
    $gate->enforceForConfirmation($opportunity);

    $spy->shouldHaveReceived('detected')->once();
});

it('allows a Block policy to be overridden by the ignore permission, recording an acknowledgement', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $user->givePermissionTo('shortages.ignore');
    $this->actingAs($user);

    $opportunity = shortQuotation(ShortagePolicy::Block);

    $result = (new ConvertToOrder)($opportunity);

    expect($result->state)->toBe(OpportunityState::Order->value)
        ->and(ShortageAcknowledgement::query()->count())->toBe(1);

    $ack = ShortageAcknowledgement::query()->first();
    expect($ack->policy_at_time)->toBe(ShortagePolicy::Block)
        ->and($ack->permission_used)->toBeTrue()
        ->and($ack->shortages_snapshot)->toHaveCount(1);
});

it('allows conversion under a Warn policy and records an acknowledgement', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $opportunity = shortQuotation(ShortagePolicy::Warn);

    $result = (new ConvertToOrder)($opportunity);

    expect($result->state)->toBe(OpportunityState::Order->value)
        ->and(ShortageAcknowledgement::query()->count())->toBe(1);

    expect(ShortageAcknowledgement::query()->first()->permission_used)->toBeFalse();
});

it('allows conversion under an Allow policy without recording an acknowledgement', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $opportunity = shortQuotation(ShortagePolicy::Allow);

    $result = (new ConvertToOrder)($opportunity);

    expect($result->state)->toBe(OpportunityState::Order->value)
        ->and(ShortageAcknowledgement::query()->count())->toBe(0);
});

it('does not block when there are no shortages', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $store = Store::factory()->shortagePolicy(ShortagePolicy::Block)->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'No shortage',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());

    $result = (new ConvertToOrder)($opportunity->fresh());

    expect($result->state)->toBe(OpportunityState::Order->value)
        ->and(ShortageAcknowledgement::query()->count())->toBe(0);
});
