<?php

use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

/*
|--------------------------------------------------------------------------
| C-data-1 — RMS-parity scalar header fields on the Opportunity
|--------------------------------------------------------------------------
|
| End-to-end coverage for the lifecycle dates, chargeable-days / open-ended
| controls, customer collect/return flags, the wired-in `invoiced` update path,
| the `source_opportunity_id` clone lineage, and the delivery/collection
| instructions. Each is exercised event → projection → response DTO, plus a full
| replay-stability assertion (the heart of the event-sourced contract).
|
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * The full lifecycle date field set (DTO key => an ISO datetime).
 *
 * @return array<string, string>
 */
function lifecycleDatePayload(): array
{
    return [
        'prep_starts_at' => '2026-07-01T08:00:00Z',
        'prep_ends_at' => '2026-07-01T10:00:00Z',
        'load_starts_at' => '2026-07-01T11:00:00Z',
        'load_ends_at' => '2026-07-01T12:00:00Z',
        'deliver_starts_at' => '2026-07-02T08:00:00Z',
        'deliver_ends_at' => '2026-07-02T10:00:00Z',
        'setup_starts_at' => '2026-07-02T11:00:00Z',
        'setup_ends_at' => '2026-07-02T14:00:00Z',
        'show_starts_at' => '2026-07-03T09:00:00Z',
        'show_ends_at' => '2026-07-05T23:00:00Z',
        'takedown_starts_at' => '2026-07-06T07:00:00Z',
        'takedown_ends_at' => '2026-07-06T10:00:00Z',
        'collect_starts_at' => '2026-07-06T11:00:00Z',
        'collect_ends_at' => '2026-07-06T13:00:00Z',
        'unload_starts_at' => '2026-07-07T08:00:00Z',
        'unload_ends_at' => '2026-07-07T09:00:00Z',
        'deprep_starts_at' => '2026-07-07T10:00:00Z',
        'deprep_ends_at' => '2026-07-07T12:00:00Z',
        'ordered_at' => '2026-06-15T09:30:00Z',
        'quote_invalid_at' => '2026-06-30T23:59:00Z',
    ];
}

it('persists the lifecycle dates + flags on create and round-trips them through the DTO', function () {
    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Festival full lifecycle',
        ...lifecycleDatePayload(),
        'use_chargeable_days' => true,
        'chargeable_days' => '2.5',
        'open_ended_rental' => true,
        'customer_collecting' => true,
        'customer_returning' => true,
        'delivery_instructions' => 'Gate B, ask for Sam',
        'collection_instructions' => 'Loading bay 3',
    ]));

    $opportunity = Opportunity::findOrFail($result->id);

    // Projection columns written.
    expect($opportunity->prep_starts_at->toIso8601String())->toBe('2026-07-01T08:00:00+00:00')
        ->and($opportunity->show_ends_at->toIso8601String())->toBe('2026-07-05T23:00:00+00:00')
        ->and($opportunity->ordered_at->toIso8601String())->toBe('2026-06-15T09:30:00+00:00')
        ->and($opportunity->quote_invalid_at->toIso8601String())->toBe('2026-06-30T23:59:00+00:00')
        ->and((bool) $opportunity->use_chargeable_days)->toBeTrue()
        ->and((string) $opportunity->chargeable_days)->toBe('2.5')
        ->and((bool) $opportunity->open_ended_rental)->toBeTrue()
        ->and((bool) $opportunity->customer_collecting)->toBeTrue()
        ->and((bool) $opportunity->customer_returning)->toBeTrue()
        ->and($opportunity->delivery_instructions)->toBe('Gate B, ask for Sam')
        ->and($opportunity->collection_instructions)->toBe('Loading bay 3');

    // Response DTO emits ISO-8601 UTC dates + the flags/decimal string.
    $data = OpportunityData::fromModel($opportunity);
    expect($data->prep_starts_at)->toBe('2026-07-01T08:00:00.000Z')
        ->and($data->deprep_ends_at)->toBe('2026-07-07T12:00:00.000Z')
        ->and($data->ordered_at)->toBe('2026-06-15T09:30:00.000Z')
        ->and($data->use_chargeable_days)->toBeTrue()
        ->and($data->chargeable_days)->toBe('2.5')
        ->and($data->open_ended_rental)->toBeTrue()
        ->and($data->customer_collecting)->toBeTrue()
        ->and($data->customer_returning)->toBeTrue()
        ->and($data->delivery_instructions)->toBe('Gate B, ask for Sam')
        ->and($data->collection_instructions)->toBe('Loading bay 3');
});

it('updates the lifecycle dates + flags through the update path', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'To schedule']));

    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from([
            'deliver_starts_at' => '2026-08-01T07:00:00Z',
            'collect_ends_at' => '2026-08-03T18:00:00Z',
            'use_chargeable_days' => true,
            'chargeable_days' => '1.5',
            'customer_collecting' => true,
            'delivery_instructions' => 'Round the back',
        ]),
    );

    expect($result->deliver_starts_at)->toBe('2026-08-01T07:00:00.000Z')
        ->and($result->collect_ends_at)->toBe('2026-08-03T18:00:00.000Z')
        ->and($result->use_chargeable_days)->toBeTrue()
        ->and($result->chargeable_days)->toBe('1.5')
        ->and($result->customer_collecting)->toBeTrue()
        ->and($result->delivery_instructions)->toBe('Round the back');

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'use_chargeable_days' => true,
        'customer_collecting' => true,
        'delivery_instructions' => 'Round the back',
    ]);
});

it('leaves an unprovided lifecycle date untouched on update', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Keep my deliver date',
        'deliver_starts_at' => '2026-09-01T08:00:00Z',
    ]));

    // Update only the customer flag — deliver_starts_at must persist.
    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['customer_returning' => true]),
    );

    $opportunity = Opportunity::findOrFail($created->id);
    expect($opportunity->deliver_starts_at->toIso8601String())->toBe('2026-09-01T08:00:00+00:00')
        ->and((bool) $opportunity->customer_returning)->toBeTrue();
});

it('clears a chargeable-days value with an explicit null on update', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Has chargeable days',
        'use_chargeable_days' => true,
        'chargeable_days' => '3.0',
        'delivery_instructions' => 'Clear me',
    ]));

    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        // Explicit null clears the Optional-backed columns.
        UpdateOpportunityData::from(['chargeable_days' => null, 'delivery_instructions' => null]),
    );

    expect($result->chargeable_days)->toBeNull()
        ->and($result->delivery_instructions)->toBeNull();
});

it('wires the invoiced flag into the update path', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Invoice me']));

    // The column defaults to false.
    expect((bool) Opportunity::findOrFail($created->id)->invoiced)->toBeFalse();

    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['invoiced' => true]),
    );

    expect($result->invoiced)->toBeTrue();
    $this->assertDatabaseHas('opportunities', ['id' => $created->id, 'invoiced' => true]);

    // And it can be flipped back off.
    $reverted = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['invoiced' => false]),
    );
    expect($reverted->invoiced)->toBeFalse();
});

it('records the source_opportunity_id when an opportunity is cloned', function () {
    $source = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Original to clone',
        'deliver_starts_at' => '2026-10-01T08:00:00Z',
    ]));

    $clone = (new CloneOpportunity)(Opportunity::findOrFail($source->id));

    $cloneModel = Opportunity::findOrFail($clone->id);
    expect($cloneModel->source_opportunity_id)->toBe($source->id)
        ->and($clone->source_opportunity_id)->toBe($source->id)
        // The BelongsTo resolves back to the original.
        ->and($cloneModel->sourceOpportunity->id)->toBe($source->id);

    // A directly-created opportunity carries no lineage.
    expect(Opportunity::findOrFail($source->id)->source_opportunity_id)->toBeNull();
});

it('rebuilds the new scalar fields identically on a full replay', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay scalars',
        ...lifecycleDatePayload(),
        'use_chargeable_days' => true,
        'chargeable_days' => '2.5',
        'open_ended_rental' => true,
        'customer_collecting' => true,
        'delivery_instructions' => 'Replay-stable note',
    ]));

    // Mutate via the update path so the replay must fold both events.
    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from([
            'customer_returning' => true,
            'invoiced' => true,
            'collection_instructions' => 'Updated collection note',
        ]),
    );

    $columns = [
        'prep_starts_at', 'deprep_ends_at', 'ordered_at', 'quote_invalid_at',
        'use_chargeable_days', 'chargeable_days', 'open_ended_rental',
        'customer_collecting', 'customer_returning', 'invoiced',
        'delivery_instructions', 'collection_instructions',
    ];

    $before = Opportunity::findOrFail($created->id)->only($columns);

    // Wipe the projection, rebuild purely from the event store.
    Opportunity::query()->withTrashed()->forceDelete();
    expect(Opportunity::query()->withTrashed()->count())->toBe(0);

    Verbs::replay();

    $after = Opportunity::findOrFail($created->id)->only($columns);

    expect($after)->toEqual($before)
        ->and((bool) $after['customer_returning'])->toBeTrue()
        ->and((bool) $after['invoiced'])->toBeTrue()
        ->and($after['collection_instructions'])->toBe('Updated collection note');
});
