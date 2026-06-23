<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortagePolicy;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ShortageAcknowledgement;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });
});

/**
 * A Quotation short by 2 (5 held, 4 committed elsewhere, line wants 3) at a store
 * under the given policy. Built as the given actor so the demand source binds.
 */
function shortGateQuotation(User $actor, ShortagePolicy $policy): Opportunity
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
            'source_id' => 999001,
            'metadata' => [],
        ]);

    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Gate API test',
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
    } finally {
        Auth::logout();
    }
}

describe('GET /api/v1/opportunities/{opportunity}/shortage_gate', function () {
    it('returns a Block decision with the shortages and records no side effects', function () {
        // A non-owner without the ignore permission — the store policy stands.
        $user = User::factory()->create();
        $user->assignRole('Sales');
        $opportunity = shortGateQuotation($user, ShortagePolicy::Block);

        $token = $user->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}/shortage_gate")
            ->assertOk();

        expect($response->json('decision'))->toBe('block')
            ->and($response->json('store_policy'))->toBe('block')
            ->and($response->json('permission_used'))->toBeFalse()
            ->and($response->json('would_block'))->toBeTrue()
            ->and($response->json('acknowledgement_required'))->toBeFalse()
            ->and($response->json('shortages'))->toHaveCount(1)
            ->and($response->json('shortages.0.shortfall'))->toBe(2);

        // Pure read: no acknowledgement was recorded.
        expect(ShortageAcknowledgement::query()->count())->toBe(0);
    });

    it('returns a Warn decision requiring acknowledgement (still no side effects)', function () {
        $user = User::factory()->create();
        $user->assignRole('Sales');
        $opportunity = shortGateQuotation($user, ShortagePolicy::Warn);

        $token = $user->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}/shortage_gate")
            ->assertOk();

        expect($response->json('decision'))->toBe('warn')
            ->and($response->json('would_block'))->toBeFalse()
            ->and($response->json('acknowledgement_required'))->toBeTrue()
            ->and($response->json('shortages'))->toHaveCount(1);

        expect(ShortageAcknowledgement::query()->count())->toBe(0);
    });

    it('returns an Allow decision that neither blocks nor requires acknowledgement', function () {
        $user = User::factory()->create();
        $user->assignRole('Sales');
        $opportunity = shortGateQuotation($user, ShortagePolicy::Allow);

        $token = $user->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}/shortage_gate")
            ->assertOk();

        expect($response->json('decision'))->toBe('allow')
            ->and($response->json('would_block'))->toBeFalse()
            ->and($response->json('acknowledgement_required'))->toBeFalse();
    });

    it('requires the shortages:read ability', function () {
        $user = User::factory()->create();
        $user->assignRole('Sales');
        $opportunity = shortGateQuotation($user, ShortagePolicy::Block);

        $token = $user->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}/shortage_gate")
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $opportunity = Opportunity::factory()->create();

        $this->getJson("/api/v1/opportunities/{$opportunity->id}/shortage_gate")
            ->assertUnauthorized();
    });
});
