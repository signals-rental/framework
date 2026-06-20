<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Members\AddressData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Address;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

/*
|--------------------------------------------------------------------------
| C-data-2 — delivery + collection address FKs on the Opportunity
|--------------------------------------------------------------------------
|
| Covers the event → projection → response DTO flow for the two address FK
| columns, the `?include=deliveryAddress,collectionAddress` lazy AddressData
| path, the new `what3words` address column, and replay-stability of the FKs.
|
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * A member with two addresses, returned as [member, deliveryAddress, collectionAddress].
 *
 * @return array{0: Member, 1: Address, 2: Address}
 */
function memberWithAddresses(): array
{
    $member = Member::factory()->organisation()->create();

    $delivery = $member->addresses()->create([
        'name' => 'Site Gate',
        'street' => '1 Festival Field',
        'city' => 'Glastonbury',
        'postcode' => 'BA6 8JG',
        'what3words' => 'filled.count.soap',
    ]);

    $collection = $member->addresses()->create([
        'name' => 'Depot',
        'street' => '99 Return Road',
        'city' => 'Bristol',
        'postcode' => 'BS1 4ST',
    ]);

    return [$member, $delivery, $collection];
}

it('stores what3words on an address and round-trips it through AddressData', function () {
    [, $delivery] = memberWithAddresses();

    $this->assertDatabaseHas('addresses', [
        'id' => $delivery->id,
        'what3words' => 'filled.count.soap',
    ]);

    expect(AddressData::fromModel($delivery->fresh())->what3words)
        ->toBe('filled.count.soap');
});

it('persists the address FKs on create and round-trips them through the DTO', function () {
    [$member, $delivery, $collection] = memberWithAddresses();

    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Festival hire with addresses',
        'member_id' => $member->id,
        'delivery_address_id' => $delivery->id,
        'collection_address_id' => $collection->id,
    ]));

    $opportunity = Opportunity::findOrFail($result->id);

    expect($opportunity->delivery_address_id)->toBe($delivery->id)
        ->and($opportunity->collection_address_id)->toBe($collection->id)
        // BelongsTo relations resolve.
        ->and($opportunity->deliveryAddress->id)->toBe($delivery->id)
        ->and($opportunity->collectionAddress->id)->toBe($collection->id);

    $data = OpportunityData::fromModel($opportunity);
    expect($data->delivery_address_id)->toBe($delivery->id)
        ->and($data->collection_address_id)->toBe($collection->id);
});

it('updates the address FKs through the update path', function () {
    [$member, $delivery, $collection] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'To address',
        'member_id' => $member->id,
    ]));

    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from([
            'delivery_address_id' => $delivery->id,
            'collection_address_id' => $collection->id,
        ]),
    );

    expect($result->delivery_address_id)->toBe($delivery->id)
        ->and($result->collection_address_id)->toBe($collection->id);

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'delivery_address_id' => $delivery->id,
        'collection_address_id' => $collection->id,
    ]);
});

it('clears an address FK with an explicit null on update', function () {
    [$member, $delivery] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Has a delivery address',
        'member_id' => $member->id,
        'delivery_address_id' => $delivery->id,
    ]));

    expect(Opportunity::findOrFail($created->id)->delivery_address_id)->toBe($delivery->id);

    // An explicit null clears the Optional-backed FK column.
    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['delivery_address_id' => null]),
    );

    expect($result->delivery_address_id)->toBeNull();
    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'delivery_address_id' => null,
    ]);
});

it('leaves an unprovided address FK untouched on update', function () {
    [$member, $delivery] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Keep my delivery address',
        'member_id' => $member->id,
        'delivery_address_id' => $delivery->id,
    ]));

    // Update only the subject — delivery_address_id must persist.
    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['subject' => 'Renamed']),
    );

    expect(Opportunity::findOrFail($created->id)->delivery_address_id)->toBe($delivery->id);
});

it('returns AddressData on the deliveryAddress/collectionAddress include path', function () {
    [$member, $delivery, $collection] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Include addresses',
        'member_id' => $member->id,
        'delivery_address_id' => $delivery->id,
        'collection_address_id' => $collection->id,
    ]));

    $opportunity = Opportunity::findOrFail($created->id)
        ->load(['deliveryAddress', 'collectionAddress']);

    $array = OpportunityData::fromModel($opportunity)->include('delivery_address', 'collection_address')->toArray();

    expect($array['delivery_address'])->toBeArray()
        ->and($array['delivery_address']['id'])->toBe($delivery->id)
        ->and($array['delivery_address']['what3words'])->toBe('filled.count.soap')
        ->and($array['collection_address']['id'])->toBe($collection->id);
});

/*
|--------------------------------------------------------------------------
| Security — IDOR / address-scoping (HIGH)
|--------------------------------------------------------------------------
|
| A delivery/collection address must belong to the opportunity's own member.
| Both layers are tested: the DTO exists-rule scoping (422 via the action's
| `::from()` validation) and the authoritative action guard (which also fires
| even when member_id is spoofed/omitted past the DTO rule).
|
*/

use Illuminate\Validation\ValidationException;

it('rejects a create whose delivery address belongs to a different member (IDOR)', function () {
    [$member] = memberWithAddresses();

    // An address owned by a DIFFERENT member.
    $otherMember = Member::factory()->organisation()->create();
    $foreignAddress = $otherMember->addresses()->create([
        'name' => 'Rival Depot',
        'street' => '1 Other Road',
        'city' => 'Leeds',
        'postcode' => 'LS1 1AA',
    ]);

    expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'IDOR attempt',
        'member_id' => $member->id,
        'delivery_address_id' => $foreignAddress->id,
    ])))->toThrow(ValidationException::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('rejects a create whose collection address belongs to a different member (IDOR)', function () {
    [$member] = memberWithAddresses();

    $otherMember = Member::factory()->organisation()->create();
    $foreignAddress = $otherMember->addresses()->create([
        'name' => 'Rival Depot',
        'street' => '2 Other Road',
        'city' => 'Leeds',
        'postcode' => 'LS2 2BB',
    ]);

    expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'IDOR attempt',
        'member_id' => $member->id,
        'collection_address_id' => $foreignAddress->id,
    ])))->toThrow(ValidationException::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('rejects a create pointing at a non-Member-owned address', function () {
    $member = Member::factory()->organisation()->create();

    // An address whose addressable is NOT a Member (e.g. a store/other entity).
    $nonMemberAddress = Address::query()->create([
        'addressable_type' => 'App\\Models\\Store',
        'addressable_id' => 99999,
        'name' => 'Warehouse',
        'street' => '5 Industrial Way',
        'city' => 'Hull',
        'postcode' => 'HU1 1AA',
    ]);

    expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Non-member address attempt',
        'member_id' => $member->id,
        'delivery_address_id' => $nonMemberAddress->id,
    ])))->toThrow(ValidationException::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('enforces the address scope in the action even when member_id is omitted past the DTO', function () {
    // Build a DTO whose member_id is null but whose delivery_address_id belongs to
    // a real member — simulating a caller that omits member_id to slip past the
    // DTO rule. The authoritative action guard must still reject it (422).
    [$member, $delivery] = memberWithAddresses();

    $data = new CreateOpportunityData(
        subject: 'Spoofed past DTO',
        member_id: null,
        delivery_address_id: $delivery->id,
    );

    expect(fn () => (new CreateOpportunity)($data))->toThrow(ValidationException::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('rejects an update pointing the delivery address at a foreign-member address (IDOR)', function () {
    [$member] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Update IDOR',
        'member_id' => $member->id,
    ]));

    $otherMember = Member::factory()->organisation()->create();
    $foreignAddress = $otherMember->addresses()->create([
        'name' => 'Rival Depot',
        'street' => '3 Other Road',
        'city' => 'Leeds',
        'postcode' => 'LS3 3CC',
    ]);

    expect(fn () => (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['delivery_address_id' => $foreignAddress->id]),
    ))->toThrow(ValidationException::class);

    expect(Opportunity::findOrFail($created->id)->delivery_address_id)->toBeNull();
});

it('rejects an update pointing the collection address at a foreign-member address (IDOR)', function () {
    [$member] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Update IDOR collection',
        'member_id' => $member->id,
    ]));

    $otherMember = Member::factory()->organisation()->create();
    $foreignAddress = $otherMember->addresses()->create([
        'name' => 'Rival Depot',
        'street' => '4 Other Road',
        'city' => 'Leeds',
        'postcode' => 'LS4 4DD',
    ]);

    expect(fn () => (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['collection_address_id' => $foreignAddress->id]),
    ))->toThrow(ValidationException::class);

    expect(Opportunity::findOrFail($created->id)->collection_address_id)->toBeNull();
});

it('rebuilds the address FKs identically on a full replay', function () {
    [$member, $delivery, $collection] = memberWithAddresses();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay addresses',
        'member_id' => $member->id,
        'delivery_address_id' => $delivery->id,
    ]));

    // Mutate the collection FK via the update path so replay folds both events.
    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['collection_address_id' => $collection->id]),
    );

    $columns = ['delivery_address_id', 'collection_address_id'];
    $before = Opportunity::findOrFail($created->id)->only($columns);

    Opportunity::query()->withTrashed()->forceDelete();
    expect(Opportunity::query()->withTrashed()->count())->toBe(0);

    Verbs::replay();

    $after = Opportunity::findOrFail($created->id)->only($columns);

    expect($after)->toEqual($before)
        ->and($after['delivery_address_id'])->toBe($delivery->id)
        ->and($after['collection_address_id'])->toBe($collection->id);
});
