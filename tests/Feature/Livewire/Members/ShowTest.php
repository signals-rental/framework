<?php

use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

it('shows related members on the reverse-direction member too', function () {
    // Relationship created from the org side (member_id = org).
    $org = Member::factory()->organisation()->create(['name' => 'Reverse Org']);
    $contact = Member::factory()->contact()->create(['name' => 'Reverse Contact']);

    MemberRelationship::factory()->create([
        'member_id' => $org->id,
        'related_member_id' => $contact->id,
    ]);

    // Contact's show page lists the org even though the row's member_id is the org.
    Volt::test('members.show', ['member' => $contact])
        ->assertSee('Reverse Org');

    // And vice versa.
    Volt::test('members.show', ['member' => $org])
        ->assertSee('Reverse Contact');
});

it('displays the related organisation profile image when it has one', function () {
    Storage::fake('public');

    $contact = Member::factory()->contact()->create(['name' => 'Imaged Contact']);
    $org = Member::factory()->organisation()->create([
        'name' => 'Imaged Org',
        'icon_thumb_url' => 'icons/org-thumb.jpg',
    ]);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Volt::test('members.show', ['member' => $contact])
        ->assertSee('s-avatar-img', false)
        ->assertSee('icons/org-thumb.jpg', false);
});

it('displays the related contact profile image when it has one', function () {
    Storage::fake('public');

    $org = Member::factory()->organisation()->create(['name' => 'Plain Org']);
    $contact = Member::factory()->contact()->create([
        'name' => 'Imaged Contact',
        'icon_thumb_url' => 'icons/contact-thumb.jpg',
    ]);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Volt::test('members.show', ['member' => $org])
        ->assertSee('s-avatar-img', false)
        ->assertSee('icons/contact-thumb.jpg', false);
});

it('mounts the merge modal and offers Merge for a non-user member', function () {
    $member = Member::factory()->organisation()->create();

    // Asserts the nested Livewire component is rendered: matches the escaped
    // component-name marker that assertSeeLivewire() looks for in the snapshot.
    Volt::test('members.show', ['member' => $member])
        ->assertSeeHtml('&quot;name&quot;:&quot;members.merge-modal&quot;')
        ->assertSee('Merge with...');
});

it('hides the Merge action for a user-type member', function () {
    $member = Member::factory()->user()->create();

    Volt::test('members.show', ['member' => $member])
        ->assertDontSee('Merge with...');
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
