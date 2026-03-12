<?php

use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the create relationship form for a contact', function () {
    $contact = Member::factory()->contact()->create();

    $this->get("/members/{$contact->id}/relationships/create")
        ->assertOk()
        ->assertSee('Add Relationship');
});

it('renders the create relationship form for an organisation', function () {
    $org = Member::factory()->organisation()->create();

    $this->get("/members/{$org->id}/relationships/create")
        ->assertOk()
        ->assertSee('Add Relationship');
});

it('can create a relationship from a contact to an organisation', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create(['name' => 'Target Org']);

    Volt::test('members.relationship-form', ['member' => $contact])
        ->set('relatedMemberId', $org->id)
        ->set('relationshipType', 'Employee')
        ->call('save')
        ->assertRedirect();

    expect(MemberRelationship::where('member_id', $contact->id)
        ->where('related_member_id', $org->id)
        ->where('relationship_type', 'Employee')
        ->exists()
    )->toBeTrue();
});

it('can create a relationship from an organisation to a contact', function () {
    $org = Member::factory()->organisation()->create();
    $contact = Member::factory()->contact()->create(['name' => 'Target Contact']);

    Volt::test('members.relationship-form', ['member' => $org])
        ->set('relatedMemberId', $contact->id)
        ->set('relationshipType', 'Contractor')
        ->call('save')
        ->assertRedirect();

    // When created from an org, the contact becomes the member_id
    expect(MemberRelationship::where('member_id', $contact->id)
        ->where('related_member_id', $org->id)
        ->where('relationship_type', 'Contractor')
        ->exists()
    )->toBeTrue();
});

it('validates required related member', function () {
    $contact = Member::factory()->contact()->create();

    Volt::test('members.relationship-form', ['member' => $contact])
        ->set('relatedMemberId', null)
        ->call('save')
        ->assertHasErrors(['relatedMemberId']);
});

it('can create a primary relationship', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    Volt::test('members.relationship-form', ['member' => $contact])
        ->set('relatedMemberId', $org->id)
        ->set('relationshipType', 'Employee')
        ->set('isPrimary', true)
        ->call('save')
        ->assertRedirect();

    expect(MemberRelationship::where('member_id', $contact->id)
        ->where('related_member_id', $org->id)
        ->first()
        ->is_primary
    )->toBeTrue();
});

it('unsets previous primary when creating a new primary relationship', function () {
    $contact = Member::factory()->contact()->create();
    $org1 = Member::factory()->organisation()->create();
    $org2 = Member::factory()->organisation()->create();

    $existing = MemberRelationship::factory()->primary()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org1->id,
    ]);

    Volt::test('members.relationship-form', ['member' => $contact])
        ->set('relatedMemberId', $org2->id)
        ->set('isPrimary', true)
        ->call('save')
        ->assertRedirect();

    expect($existing->fresh()->is_primary)->toBeFalse();
});

it('requires authentication', function () {
    $contact = Member::factory()->contact()->create();
    auth()->logout();

    $this->get("/members/{$contact->id}/relationships/create")
        ->assertRedirect();
});
