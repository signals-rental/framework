<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
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
    $this->owner = User::factory()->owner()->create();

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    // 4 units committed elsewhere — only 1 free.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 777001,
            'metadata' => [],
        ]);

    // A quotation wanting 3 of the product — short by 2.
    $this->opportunity = createShortOpportunity($this->owner, $this->store, $this->product);
    $this->item = $this->opportunity->items->first();
});

function createShortOpportunity(User $actor, Store $store, Product $product): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'API shortage',
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

        return $opportunity->fresh(['items']);
    } finally {
        Auth::logout();
    }
}

describe('GET /api/v1/opportunities/{opportunity}/shortages', function () {
    it('returns detected shortages with the read ability', function () {
        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortages")
            ->assertOk();

        expect($response->json('shortages'))->toHaveCount(1)
            ->and($response->json('shortages.0.shortfall'))->toBe(2)
            ->and($response->json('shortages.0.remaining_shortfall'))->toBe(2)
            ->and($response->json('shortages.0.tracking_type'))->toBe('bulk');
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortages")
            ->assertUnauthorized();
    });

    it('requires the shortages:read ability', function () {
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortages")
            ->assertForbidden();
    });
});

describe('GET shortage resolvers', function () {
    it('lists applicable resolver options for a short line item', function () {
        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/items/{$this->item->id}/shortage_resolvers")
            ->assertOk();

        $response->assertJsonFragment(['resolver_key' => 'partial'])
            ->assertJsonFragment(['resolver_key' => 'waitlist']);
    });

    it('404s for an item that does not belong to the opportunity', function () {
        $other = OpportunityItem::factory()->create();
        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/items/{$other->id}/shortage_resolvers")
            ->assertNotFound();
    });
});

describe('POST /api/v1/shortage_resolutions', function () {
    it('applies a resolver and records a resolution with the write ability', function () {
        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shortage_resolutions', [
                'opportunity_item_id' => $this->item->id,
                'resolver_key' => 'partial',
            ])
            ->assertCreated();

        expect($response->json('resolution.resolver_key'))->toBe('partial')
            ->and($response->json('status'))->toBe('confirmed');

        $this->assertDatabaseHas('shortage_resolutions', [
            'id' => $response->json('resolution.id'),
            'resolver_key' => 'partial',
        ]);
    });

    it('requires the shortages:write ability', function () {
        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shortage_resolutions', [
                'opportunity_item_id' => $this->item->id,
                'resolver_key' => 'partial',
            ])
            ->assertForbidden();
    });

    it('422s when the item has no current shortage', function () {
        // Free up all stock so the line is serviceable.
        Demand::query()->where('source_id', 777001)->delete();

        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shortage_resolutions', [
                'opportunity_item_id' => $this->item->id,
                'resolver_key' => 'partial',
            ])
            ->assertStatus(422);
    });

    it('validates the request body', function () {
        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shortage_resolutions', [])
            ->assertStatus(422);
    });
});

describe('POST shortage acknowledgement', function () {
    it('records an acknowledgement with the write ability', function () {
        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$this->opportunity->id}/shortages/acknowledge", [
                'notes' => 'Customer accepts the risk.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('shortage_acknowledgements', [
            'opportunity_id' => $this->opportunity->id,
            'notes' => 'Customer accepts the risk.',
        ]);
    });

    it('422s when there are no shortages to acknowledge', function () {
        Demand::query()->where('source_id', 777001)->delete();

        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$this->opportunity->id}/shortages/acknowledge", [])
            ->assertStatus(422);
    });
});
