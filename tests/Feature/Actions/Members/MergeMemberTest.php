<?php

use App\Actions\Members\MergeMember;
use App\Data\Members\MergeMemberData;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Address;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Email;
use App\Models\Link;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Membership;
use App\Models\Phone;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('merges two members of the same type', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $primary = Member::factory()->organisation()->create(['name' => 'Primary Org']);
    $secondary = Member::factory()->organisation()->create(['name' => 'Secondary Org']);

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    $result = (new MergeMember)($data);

    expect($result->id)->toBe($primary->id)
        ->and($result->name)->toBe('Primary Org');

    // Secondary should be soft-deleted (archived)
    expect(Member::find($secondary->id))->toBeNull();
    expect(Member::withTrashed()->find($secondary->id))->not->toBeNull();
});

it('migrates addresses, emails, phones, and links from secondary to primary', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $secondary->id,
    ]);
    Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $secondary->id,
    ]);
    Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $secondary->id,
    ]);
    Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $secondary->id,
    ]);

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);

    expect($primary->addresses()->count())->toBe(1)
        ->and($primary->emails()->count())->toBe(1)
        ->and($primary->phones()->count())->toBe(1)
        ->and($primary->links()->count())->toBe(1);

    // Secondary should have no relations left
    $secondaryTrashed = Member::withTrashed()->find($secondary->id);
    expect($secondaryTrashed->addresses()->count())->toBe(0)
        ->and($secondaryTrashed->emails()->count())->toBe(0)
        ->and($secondaryTrashed->phones()->count())->toBe(0)
        ->and($secondaryTrashed->links()->count())->toBe(0);
});

it('archives the secondary member after merge', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $primary = Member::factory()->contact()->create();
    $secondary = Member::factory()->contact()->create(['is_active' => true]);

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);

    $archivedSecondary = Member::withTrashed()->find($secondary->id);
    expect($archivedSecondary->is_active)->toBeFalse()
        ->and($archivedSecondary->deleted_at)->not->toBeNull();
});

it('rejects merging members of different types', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $contact = Member::factory()->contact()->create();
    $organisation = Member::factory()->organisation()->create();

    $data = MergeMemberData::from([
        'primary_id' => $contact->id,
        'secondary_id' => $organisation->id,
    ]);

    (new MergeMember)($data);
})->throws(\InvalidArgumentException::class, 'Cannot merge members of different types.');

it('denies unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);
})->throws(AuthorizationException::class);

it('fires AuditableEvent with member.merged action and metadata', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $primary = Member::factory()->organisation()->create(['name' => 'Primary']);
    $secondary = Member::factory()->organisation()->create(['name' => 'Secondary']);

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) use ($primary, $secondary) {
        return $event->action === 'member.merged'
            && $event->model->getKey() === $primary->id
            && $event->metadata['secondary_id'] === $secondary->id
            && $event->metadata['secondary_name'] === 'Secondary';
    });
});

it('skips duplicate member relationships during merge', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $primary = Member::factory()->contact()->create();
    $secondary = Member::factory()->contact()->create();
    $sharedOrg = Member::factory()->organisation()->create();

    // Both have relationships to the same organisation
    MemberRelationship::factory()
        ->for($primary, 'member')
        ->for($sharedOrg, 'relatedMember')
        ->create();
    MemberRelationship::factory()
        ->for($secondary, 'member')
        ->for($sharedOrg, 'relatedMember')
        ->create();

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);

    // Primary should still only have one relationship to sharedOrg (no duplicate)
    $relationshipCount = MemberRelationship::where('member_id', $primary->id)
        ->where('related_member_id', $sharedOrg->id)
        ->count();
    expect($relationshipCount)->toBe(1);
});

it('copies missing custom field values without overwriting existing ones', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $fieldA = CustomField::factory()->string()->create([
        'name' => 'field_a',
        'module_type' => 'Member',
    ]);
    $fieldB = CustomField::factory()->string()->create([
        'name' => 'field_b',
        'module_type' => 'Member',
    ]);

    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    // Primary has field_a, secondary has both field_a and field_b
    $primary->syncCustomFields(['field_a' => 'Primary A Value']);
    $secondary->syncCustomFields(['field_a' => 'Secondary A Value', 'field_b' => 'Secondary B Value']);

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);

    // Primary's field_a should remain unchanged (not overwritten)
    $primaryFieldA = CustomFieldValue::query()
        ->where('entity_type', Member::class)
        ->where('entity_id', $primary->id)
        ->where('custom_field_id', $fieldA->id)
        ->first();
    expect($primaryFieldA->value_string)->toBe('Primary A Value');

    // Primary should now have field_b from secondary
    $primaryFieldB = CustomFieldValue::query()
        ->where('entity_type', Member::class)
        ->where('entity_id', $primary->id)
        ->where('custom_field_id', $fieldB->id)
        ->first();
    expect($primaryFieldB)->not->toBeNull()
        ->and($primaryFieldB->value_string)->toBe('Secondary B Value');
});

it('migrates memberships and skips duplicate store assignments', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $primary = Member::factory()->organisation()->create();
    $secondary = Member::factory()->organisation()->create();

    $sharedStore = Store::factory()->create();
    $uniqueStore = Store::factory()->create();

    // Both members belong to sharedStore, only secondary belongs to uniqueStore
    Membership::factory()->forStore($sharedStore)->create(['member_id' => $primary->id]);
    Membership::factory()->forStore($sharedStore)->create(['member_id' => $secondary->id]);
    Membership::factory()->forStore($uniqueStore)->create(['member_id' => $secondary->id]);

    $data = MergeMemberData::from([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);

    (new MergeMember)($data);

    // Primary should have membership in both stores (not duplicated for sharedStore)
    $primaryMemberships = Membership::where('member_id', $primary->id)->get();
    expect($primaryMemberships)->toHaveCount(2);
    expect($primaryMemberships->pluck('store_id')->sort()->values()->toArray())
        ->toBe(collect([$sharedStore->id, $uniqueStore->id])->sort()->values()->toArray());
});
