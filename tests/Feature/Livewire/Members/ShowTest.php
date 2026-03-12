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

it('renders the show page with member details', function () {
    $member = Member::factory()->contact()->create(['name' => 'John Smith']);

    $this->get("/members/{$member->id}")
        ->assertOk()
        ->assertSee('John Smith');
});

it('shows the member name and type', function () {
    $member = Member::factory()->organisation()->create(['name' => 'Acme Corp']);

    Volt::test('members.show', ['member' => $member])
        ->assertSee('Acme Corp')
        ->assertSee('Organisation');
});

it('shows active badge for active members', function () {
    $member = Member::factory()->create(['name' => 'Active Person', 'is_active' => true]);

    Volt::test('members.show', ['member' => $member])
        ->assertSee('Active');
});

it('shows inactive badge for inactive members', function () {
    $member = Member::factory()->inactive()->create(['name' => 'Inactive Person']);

    Volt::test('members.show', ['member' => $member])
        ->assertSee('Inactive');
});

it('shows organisation links for contacts', function () {
    $contact = Member::factory()->contact()->create(['name' => 'John Contact']);
    $org = Member::factory()->organisation()->create(['name' => 'Linked Org']);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Volt::test('members.show', ['member' => $contact])
        ->assertSee('Linked Org');
});

it('shows contact links for organisations', function () {
    $org = Member::factory()->organisation()->create(['name' => 'Parent Org']);
    $contact = Member::factory()->contact()->create(['name' => 'Linked Contact']);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Volt::test('members.show', ['member' => $org])
        ->assertSee('Linked Contact');
});

it('shows description when present', function () {
    $member = Member::factory()->create([
        'name' => 'Descriptive Member',
        'description' => 'A useful description',
    ]);

    Volt::test('members.show', ['member' => $member])
        ->assertSee('A useful description');
});

it('requires authentication', function () {
    $member = Member::factory()->create();
    auth()->logout();

    $this->get("/members/{$member->id}")
        ->assertRedirect();
});
