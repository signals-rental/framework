<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

/**
 * R-A write-path coverage (master M1/B7+NF1+NF3, M2/B3+B3b): the charge-date,
 * tag_list, and custom_fields write paths run through the OpportunityCreated /
 * OpportunityUpdated events and project onto the row, and mutation responses
 * return the populated custom_fields object rather than an empty {}.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function raWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

describe('charge dates + tag_list write path', function () {
    it('persists charge_starts_at, charge_ends_at and tag_list on create via the event', function () {
        $token = raWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', [
                'subject' => 'Tagged Job',
                'charge_starts_at' => '2026-07-01T09:00:00Z',
                'charge_ends_at' => '2026-07-05T17:00:00Z',
                'tag_list' => ['vip', 'rush'],
            ])
            ->assertCreated()
            ->assertJsonPath('opportunity.tag_list', ['vip', 'rush']);

        $id = $response->json('opportunity.id');

        expect($response->json('opportunity.charge_starts_at'))->not->toBeNull()
            ->and($response->json('opportunity.charge_ends_at'))->not->toBeNull();

        $opportunity = Opportunity::query()->findOrFail($id);
        expect($opportunity->charge_starts_at)->not->toBeNull()
            ->and($opportunity->charge_ends_at)->not->toBeNull()
            ->and($opportunity->tag_list)->toBe(['vip', 'rush']);
    });

    it('updates charge dates and tag_list on update via the event', function () {
        $opportunity = raCreateOpportunity($this->owner, ['subject' => 'Before', 'tag_list' => ['old']]);
        $token = raWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", [
                'charge_starts_at' => '2026-08-01T09:00:00Z',
                'charge_ends_at' => '2026-08-02T09:00:00Z',
                'tag_list' => ['new', 'shiny'],
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.tag_list', ['new', 'shiny']);

        $opportunity->refresh();
        expect($opportunity->tag_list)->toBe(['new', 'shiny'])
            ->and($opportunity->charge_starts_at)->not->toBeNull();
    });

    it('clears tag_list when an explicit empty array is supplied on update', function () {
        $opportunity = raCreateOpportunity($this->owner, ['tag_list' => ['keep']]);
        $token = raWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['tag_list' => []])
            ->assertOk()
            ->assertJsonPath('opportunity.tag_list', []);

        expect($opportunity->refresh()->tag_list)->toBe([]);
    });

    it('leaves tag_list untouched when the key is absent on update', function () {
        $opportunity = raCreateOpportunity($this->owner, ['tag_list' => ['sticky']]);
        $token = raWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'Renamed'])
            ->assertOk();

        expect($opportunity->refresh()->tag_list)->toBe(['sticky']);
    });
});

describe('custom_fields on mutation responses', function () {
    beforeEach(function () {
        $this->cf = CustomField::factory()->create([
            'module_type' => 'Opportunity',
            'name' => 'po_reference',
            'field_type' => CustomFieldType::String,
            'is_searchable' => true,
        ]);
    });

    it('round-trips custom_fields on create and returns them populated', function () {
        $token = raWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', [
                'subject' => 'CF Job',
                'custom_fields' => ['po_reference' => 'PO-123'],
            ])
            ->assertCreated()
            ->assertJsonPath('opportunity.custom_fields.po_reference', 'PO-123');
    });

    it('returns populated custom_fields on a write response (not an empty object)', function () {
        $opportunity = raCreateOpportunity($this->owner, [
            'custom_fields' => ['po_reference' => 'PO-999'],
        ]);
        $token = raWriteToken($this->owner);

        // A subsequent header update must still echo the stored custom fields,
        // proving respondWithFreshOpportunity eager-loads customFieldValues.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'Touched'])
            ->assertOk()
            ->assertJsonPath('opportunity.custom_fields.po_reference', 'PO-999');
    });

    it('updates custom_fields via the update action', function () {
        $opportunity = raCreateOpportunity($this->owner, [
            'custom_fields' => ['po_reference' => 'PO-1'],
        ]);
        $token = raWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", [
                'custom_fields' => ['po_reference' => 'PO-2'],
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.custom_fields.po_reference', 'PO-2');
    });
});

/**
 * Create an opportunity through the real event pipeline as the actor, then log
 * out so a tokened HTTP request authenticates solely via its bearer token.
 *
 * @param  array<string, mixed>  $attributes
 */
function raCreateOpportunity(User $actor, array $attributes = []): Opportunity
{
    Auth::login($actor);

    try {
        $data = CreateOpportunityData::from(array_merge(['subject' => 'RA Test'], $attributes));
        $result = (new CreateOpportunity)($data);

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}
