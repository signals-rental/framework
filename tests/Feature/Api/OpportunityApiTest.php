<?php

use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\CustomView;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\assertSoftDeleted;

/**
 * Create an opportunity through the real event-sourcing pipeline so the row has a
 * genuine Verbs state/snapshot and can be transitioned/updated/deleted. Factory
 * rows bypass the event stream and have a synthetic state_id, so they are only
 * safe for read-only (index/show) assertions.
 *
 * The lifecycle actions authorize via Gate, so a user is logged in for the
 * duration of the action and then logged out — the subsequent HTTP request must
 * authenticate solely via its Sanctum bearer token (otherwise a lingering
 * session user would bypass the token-ability check under auth:sanctum).
 */
/**
 * @param  array<string, mixed>  $attributes
 */
function createOpportunityViaEvent(User $actor, array $attributes = []): Opportunity
{
    Auth::login($actor);

    try {
        $data = CreateOpportunityData::from(array_merge(['subject' => 'Test Opportunity'], $attributes));
        $result = (new CreateOpportunity)($data);

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

/**
 * Run a lifecycle action against an opportunity as an authenticated actor, then
 * log out so the session does not leak into a tokened HTTP request.
 *
 * @param  callable(): mixed  $fn
 */
function asAuthenticated(User $actor, callable $fn): void
{
    Auth::login($actor);

    try {
        $fn();
    } finally {
        Auth::logout();
    }
}

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function readToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

function writeToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

describe('GET /api/v1/opportunities', function () {
    it('lists opportunities with pagination meta', function () {
        Opportunity::factory()->count(3)->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonStructure([
                'opportunities' => [
                    '*' => ['id', 'subject', 'state', 'state_label', 'status', 'status_label', 'availability_phase', 'charge_total', 'custom_fields', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by state with a Ransack _eq predicate', function () {
        Opportunity::factory()->create();
        Opportunity::factory()->quotation()->create();
        Opportunity::factory()->order()->create();
        $token = readToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?q[state_eq]='.OpportunityState::Quotation->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        expect($response->json('opportunities.0.state'))->toBe(OpportunityState::Quotation->value);
    });

    it('respects per_page pagination', function () {
        Opportunity::factory()->count(5)->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'opportunities');
    });

    it('eager-loads relationships via ?include=', function () {
        $member = Member::factory()->create();
        Opportunity::factory()->create(['member_id' => $member->id]);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?include=member')
            ->assertOk()
            ->assertJsonPath('opportunities.0.member.id', $member->id);
    });

    it('applies a saved custom view via view_id with sparse columns', function () {
        Opportunity::factory()->create(['subject' => 'View Test', 'reference' => 'PO-9']);
        $view = CustomView::query()->create([
            'name' => 'Sparse',
            'entity_type' => 'opportunities',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'columns' => ['subject', 'reference'],
            'filters' => [],
            'sort_column' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 20,
            'config' => [],
        ]);
        $token = readToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?view_id='.$view->id)
            ->assertOk()
            ->assertJsonPath('meta.view.id', $view->id);

        $first = $response->json('opportunities.0');
        expect(array_keys($first))->toEqualCanonicalizing(['id', 'subject', 'reference']);
    });

    it('requires the opportunities:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities')
            ->assertForbidden();
    });

    it('rejects an unauthenticated request', function () {
        $this->getJson('/api/v1/opportunities')->assertUnauthorized();
    });
});

describe('GET /api/v1/opportunities/{id}', function () {
    it('shows a single opportunity', function () {
        $opportunity = Opportunity::factory()->create(['subject' => 'Single']);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertJsonPath('opportunity.id', $opportunity->id)
            ->assertJsonPath('opportunity.subject', 'Single');
    });

    it('404s for a soft-deleted opportunity', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $opportunity->delete();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertNotFound();
    });
});

describe('POST /api/v1/opportunities', function () {
    it('creates an opportunity via the event and projects the row plus an audit log', function () {
        $token = writeToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', [
                'subject' => 'Glastonbury Main Stage',
                'reference' => 'PO-555',
            ])
            ->assertCreated()
            ->assertJsonPath('opportunity.subject', 'Glastonbury Main Stage')
            ->assertJsonPath('opportunity.state', OpportunityState::Draft->value)
            ->assertJsonPath('opportunity.status_label', 'Open');

        $id = $response->json('opportunity.id');

        $this->assertDatabaseHas('opportunities', [
            'id' => $id,
            'subject' => 'Glastonbury Main Stage',
            'state' => OpportunityState::Draft->value,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'opportunity.created',
            'auditable_type' => Opportunity::class,
            'auditable_id' => $id,
        ]);
    });

    it('validates a missing subject', function () {
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    });

    it('requires the opportunities:write ability', function () {
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', ['subject' => 'Nope'])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/opportunities/{id}', function () {
    it('updates header fields via the event', function () {
        $opportunity = createOpportunityViaEvent($this->owner, ['subject' => 'Before']);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'After'])
            ->assertOk()
            ->assertJsonPath('opportunity.subject', 'After');

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'subject' => 'After']);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'X'])
            ->assertForbidden();
    });
});

describe('transition endpoints', function () {
    it('converts a draft to a quotation', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_quotation")
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Quotation->value);

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'state' => OpportunityState::Quotation->value,
        ]);
    });

    it('converts a quotation to an order', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, fn () => (new ConvertToQuotation)($opportunity));
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_order")
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Order->value);
    });

    it('changes status within the current state', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, fn () => (new ConvertToQuotation)($opportunity));
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/change_status", [
                'status' => OpportunityStatus::QuotationReserved->statusValue(),
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.status', OpportunityStatus::QuotationReserved->statusValue());
    });

    it('rejects an invalid status for the current state with 422', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = writeToken($this->owner);

        // Draft only has status 0; status 5 is an Order status.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/change_status", ['status' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    it('rejects an invalid transition with 422', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, function () use ($opportunity) {
            (new ConvertToQuotation)($opportunity);
            (new ConvertToOrder)($opportunity);
        });
        $token = writeToken($this->owner);

        // Already an Order — converting to quotation is invalid.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_quotation")
            ->assertStatus(422);
    });

    it('requires the opportunities:write ability for transitions', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_quotation")
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/opportunities/{id}', function () {
    it('soft-deletes the opportunity via the event', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertNoContent();

        assertSoftDeleted('opportunities', ['id' => $opportunity->id]);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'opportunity.deleted',
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id,
        ]);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertForbidden();
    });
});
