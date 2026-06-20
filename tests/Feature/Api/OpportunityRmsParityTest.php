<?php

use App\Actions\Opportunities\AddOpportunityParticipant;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityParticipantData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Address;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

/**
 * C3i — RMS opportunity API field-parity: a full RMS-shaped create/update
 * round-trips through the event-sourced pipeline and serialises in the RMS shape
 * (decimal-string money, ISO-8601 UTC dates, numeric + named state/status, the
 * Phase-3 scalar fields, the `meta` block, and the participants include).
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function rmsWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write', 'opportunities:read'])->plainTextToken;
}

describe('RMS create round-trip', function () {
    it('creates an opportunity from a full RMS-shaped payload and serialises in the RMS shape', function () {
        $org = Member::factory()->organisation()->create();
        $delivery = Address::factory()->create([
            'addressable_type' => Member::class,
            'addressable_id' => $org->id,
            'name' => 'Delivery Dock',
        ]);
        $collection = Address::factory()->create([
            'addressable_type' => Member::class,
            'addressable_id' => $org->id,
            'name' => 'Collection Bay',
        ]);

        $token = rmsWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/opportunities', [
            'subject' => 'Summer Festival Hire',
            'member_id' => $org->id,
            'reference' => 'PO-4471',
            'description' => 'Internal notes',
            'external_description' => 'Customer-facing scope',
            'starts_at' => '2026-07-01T08:00:00Z',
            'ends_at' => '2026-07-06T18:00:00Z',
            'charge_starts_at' => '2026-07-01T00:00:00Z',
            'charge_ends_at' => '2026-07-06T23:59:00Z',
            'deliver_starts_at' => '2026-07-01T07:00:00Z',
            'collect_ends_at' => '2026-07-06T19:00:00Z',
            'ordered_at' => '2026-06-20T10:00:00Z',
            'quote_invalid_at' => '2026-06-30T23:59:00Z',
            'use_chargeable_days' => true,
            'chargeable_days' => '5',
            'open_ended_rental' => false,
            'customer_collecting' => true,
            'customer_returning' => false,
            'rating' => 4,
            'delivery_instructions' => 'Ring on arrival',
            'collection_instructions' => 'Pallets stacked at bay 3',
            'delivery_address_id' => $delivery->id,
            'collection_address_id' => $collection->id,
            'prices_include_tax' => false,
            'tag_list' => ['vip', 'festival'],
            'custom_fields' => [],
        ]);

        $response->assertCreated()
            // Identity + naming.
            ->assertJsonPath('opportunity.subject', 'Summer Festival Hire')
            ->assertJsonPath('opportunity.reference', 'PO-4471')
            ->assertJsonPath('opportunity.external_description', 'Customer-facing scope')
            ->assertJsonPath('opportunity.member_id', $org->id)
            // Two-axis state model: numeric + RMS _name labels.
            ->assertJsonPath('opportunity.state', 0)
            ->assertJsonPath('opportunity.state_name', 'Draft')
            ->assertJsonPath('opportunity.status_name', 'Open')
            // ISO-8601 UTC dates (millisecond precision, Z suffix).
            ->assertJsonPath('opportunity.starts_at', '2026-07-01T08:00:00.000Z')
            ->assertJsonPath('opportunity.deliver_starts_at', '2026-07-01T07:00:00.000Z')
            ->assertJsonPath('opportunity.ordered_at', '2026-06-20T10:00:00.000Z')
            // Phase-3 scalar parity fields.
            ->assertJsonPath('opportunity.use_chargeable_days', true)
            ->assertJsonPath('opportunity.chargeable_days', '5.0')
            ->assertJsonPath('opportunity.customer_collecting', true)
            ->assertJsonPath('opportunity.customer_returning', false)
            ->assertJsonPath('opportunity.rating', 4)
            ->assertJsonPath('opportunity.delivery_instructions', 'Ring on arrival')
            ->assertJsonPath('opportunity.delivery_address_id', $delivery->id)
            ->assertJsonPath('opportunity.collection_address_id', $collection->id)
            ->assertJsonPath('opportunity.tag_list', ['vip', 'festival'])
            // Money serialised as decimal strings.
            ->assertJsonPath('opportunity.charge_total', '0.00')
            ->assertJsonPath('opportunity.pricing_locked', false)
            // RMS meta block as a sibling of the resource.
            ->assertJsonPath('meta.can_edit', true)
            ->assertJsonPath('meta.can_destroy', true);

        expect($response->json('opportunity.charge_total'))->toBeString();

        // Persisted on the projection row.
        $opportunity = Opportunity::query()->findOrFail($response->json('opportunity.id'));
        expect($opportunity->rating)->toBe(4)
            ->and($opportunity->member_id)->toBe($org->id)
            ->and($opportunity->delivery_address_id)->toBe($delivery->id)
            ->and((bool) $opportunity->customer_collecting)->toBeTrue()
            ->and($opportunity->tag_list)->toBe(['vip', 'festival']);
    });

    it('rejects a rating outside 0–5', function () {
        $token = rmsWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', ['subject' => 'Bad rating', 'rating' => 9])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    });
});

describe('RMS update round-trip', function () {
    it('updates a representative subset and persists it in RMS shape', function () {
        $opportunity = createRmsOpportunity($this->owner, ['subject' => 'Before', 'rating' => 1]);
        $token = rmsWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", [
                'subject' => 'After',
                'reference' => 'PO-9999',
                'rating' => 5,
                'customer_returning' => true,
                'tag_list' => ['updated'],
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.subject', 'After')
            ->assertJsonPath('opportunity.reference', 'PO-9999')
            ->assertJsonPath('opportunity.rating', 5)
            ->assertJsonPath('opportunity.customer_returning', true)
            ->assertJsonPath('opportunity.tag_list', ['updated'])
            ->assertJsonPath('meta.can_edit', true);

        $opportunity->refresh();
        expect($opportunity->subject)->toBe('After')
            ->and($opportunity->rating)->toBe(5)
            ->and((bool) $opportunity->customer_returning)->toBeTrue()
            ->and($opportunity->tag_list)->toBe(['updated']);
    });

    it('clears the rating when an explicit null is supplied', function () {
        $opportunity = createRmsOpportunity($this->owner, ['rating' => 3]);
        $token = rmsWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['rating' => null])
            ->assertOk()
            ->assertJsonPath('opportunity.rating', null);

        expect($opportunity->refresh()->rating)->toBeNull();
    });

    it('leaves the rating untouched when the key is absent', function () {
        $opportunity = createRmsOpportunity($this->owner, ['rating' => 2]);
        $token = rmsWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('opportunity.rating', 2);

        expect($opportunity->refresh()->rating)->toBe(2);
    });
});

describe('RMS show shape + includes', function () {
    it('returns the meta block and the participants/address includes in the RMS shape', function () {
        $org = Member::factory()->organisation()->create();
        $contact = Member::factory()->contact()->create();
        $delivery = Address::factory()->create([
            'addressable_type' => Member::class,
            'addressable_id' => $org->id,
            'name' => 'Dock A',
        ]);

        $opportunity = createRmsOpportunity($this->owner, [
            'subject' => 'Show shape',
            'member_id' => $org->id,
            'delivery_address_id' => $delivery->id,
        ]);

        asAuthenticatedRms($this->owner, function () use ($opportunity, $contact) {
            (new AddOpportunityParticipant)(
                $opportunity,
                AddOpportunityParticipantData::from(['member_id' => $contact->id, 'role' => 'primary_contact']),
            );
        });

        $token = rmsWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}?include=participants,deliveryAddress,collectionAddress");

        $response->assertOk()
            ->assertJsonPath('opportunity.id', $opportunity->id)
            ->assertJsonPath('opportunity.state_name', 'Draft')
            ->assertJsonPath('opportunity.pricing_locked', false)
            ->assertJsonPath('opportunity.participants.0.member_id', $contact->id)
            ->assertJsonPath('opportunity.participants.0.role', 'primary_contact')
            ->assertJsonPath('opportunity.delivery_address.id', $delivery->id)
            ->assertJsonPath('meta.can_edit', true)
            ->assertJsonPath('meta.can_destroy', true);

        // collectionAddress was requested but unset — it serialises as null, present.
        expect($response->json('opportunity'))->toHaveKey('collection_address')
            ->and($response->json('opportunity.collection_address'))->toBeNull();
    });
});

/**
 * Create an opportunity through the real event pipeline as the actor, then log
 * out so a tokened HTTP request authenticates solely via its bearer token.
 *
 * @param  array<string, mixed>  $attributes
 */
function createRmsOpportunity(User $actor, array $attributes = []): Opportunity
{
    Auth::login($actor);

    try {
        $data = CreateOpportunityData::from(array_merge(['subject' => 'RMS Test'], $attributes));
        $result = (new CreateOpportunity)($data);

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

/**
 * @param  callable(): mixed  $fn
 */
function asAuthenticatedRms(User $actor, callable $fn): void
{
    Auth::login($actor);

    try {
        $fn();
    } finally {
        Auth::logout();
    }
}
