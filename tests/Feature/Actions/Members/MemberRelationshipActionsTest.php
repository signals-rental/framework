<?php

use App\Actions\Members\CreateMemberRelationship;
use App\Actions\Members\DeleteMemberRelationship;
use App\Data\Members\CreateMemberRelationshipData;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a member relationship', function () {
    Event::fake([AuditableEvent::class]);

    $contact = Member::factory()->contact()->create();
    $organisation = Member::factory()->organisation()->create();

    $data = CreateMemberRelationshipData::from([
        'related_member_id' => $organisation->id,
        'relationship_type' => 'employee',
        'is_primary' => true,
    ]);

    $result = (new CreateMemberRelationship)($contact, $data);

    expect($result->member_id)->toBe($contact->id);
    expect($result->related_member_id)->toBe($organisation->id);
    expect($result->relationship_type)->toBe('employee');
    expect($result->is_primary)->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a member relationship', function () {
    Event::fake([AuditableEvent::class]);

    $contact = Member::factory()->contact()->create();
    $organisation = Member::factory()->organisation()->create();
    $relationship = MemberRelationship::factory()
        ->for($contact, 'member')
        ->for($organisation, 'relatedMember')
        ->create();

    (new DeleteMemberRelationship)($relationship);

    expect(MemberRelationship::find($relationship->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects duplicate relationship between same members', function () {
    $contact = Member::factory()->contact()->create();
    $organisation = Member::factory()->organisation()->create();

    MemberRelationship::factory()
        ->for($contact, 'member')
        ->for($organisation, 'relatedMember')
        ->create();

    $data = CreateMemberRelationshipData::from([
        'related_member_id' => $organisation->id,
        'relationship_type' => 'employee',
    ]);

    (new CreateMemberRelationship)($contact, $data);
})->throws(\Illuminate\Validation\ValidationException::class, 'A relationship between these members already exists.');

it('rejects duplicate relationship in reverse direction', function () {
    $contact = Member::factory()->contact()->create();
    $organisation = Member::factory()->organisation()->create();

    // Create relationship in one direction
    MemberRelationship::factory()
        ->for($organisation, 'member')
        ->for($contact, 'relatedMember')
        ->create();

    // Attempt to create in the other direction
    $data = CreateMemberRelationshipData::from([
        'related_member_id' => $organisation->id,
        'relationship_type' => 'employee',
    ]);

    (new CreateMemberRelationship)($contact, $data);
})->throws(\Illuminate\Validation\ValidationException::class, 'A relationship between these members already exists.');

it('rejects unauthorized relationship creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $contact = Member::factory()->contact()->create();
    $organisation = Member::factory()->organisation()->create();

    $data = CreateMemberRelationshipData::from([
        'related_member_id' => $organisation->id,
    ]);

    (new CreateMemberRelationship)($contact, $data);
})->throws(AuthorizationException::class);
