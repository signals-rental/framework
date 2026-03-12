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

it('renders the relationships page for a contact', function () {
    $contact = Member::factory()->contact()->create();

    $this->get("/members/{$contact->id}/relationships")
        ->assertOk()
        ->assertSee('Relationships');
});

it('renders the relationships page for an organisation', function () {
    $org = Member::factory()->organisation()->create();

    $this->get("/members/{$org->id}/relationships")
        ->assertOk()
        ->assertSee('Relationships');
});

it('lists related organisations for a contact', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create(['name' => 'Acme Corp']);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
        'relationship_type' => 'Employee',
    ]);

    Volt::test('members.relationships', ['member' => $contact])
        ->assertSee('Acme Corp')
        ->assertSee('Employee');
});

it('lists related contacts for an organisation', function () {
    $org = Member::factory()->organisation()->create();
    $contact = Member::factory()->contact()->create(['name' => 'John Smith']);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
        'relationship_type' => 'Director',
    ]);

    Volt::test('members.relationships', ['member' => $org])
        ->assertSee('John Smith')
        ->assertSee('Director');
});

it('shows empty state when no relationships exist', function () {
    $member = Member::factory()->contact()->create();

    Volt::test('members.relationships', ['member' => $member])
        ->assertSee('No relationships found.');
});

it('shows primary badge on primary relationship', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    MemberRelationship::factory()->primary()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Volt::test('members.relationships', ['member' => $contact])
        ->assertSee('Primary');
});

it('can delete a relationship', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    $relationship = MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Volt::test('members.relationships', ['member' => $contact])
        ->call('deleteRelationship', $relationship->id);

    expect(MemberRelationship::find($relationship->id))->toBeNull();
});

it('requires authentication', function () {
    $member = Member::factory()->create();
    auth()->logout();

    $this->get("/members/{$member->id}/relationships")
        ->assertRedirect();
});
