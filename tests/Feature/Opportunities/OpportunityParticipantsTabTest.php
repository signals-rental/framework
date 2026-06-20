<?php

use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Opportunity Participants tab — manage members associated with an opportunity
|--------------------------------------------------------------------------
|
| Participants are plain, NON-event-sourced CRM associations, so a factory-made
| opportunity (no event stream) is sufficient for the UI panel. Every mutation
| routes through the same action classes the API uses.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->opportunity = Opportunity::factory()->create();
});

it('lists the opportunity participants', function () {
    $member = Member::factory()->contact()->create(['name' => 'Acme Contact']);
    OpportunityParticipant::factory()->for($this->opportunity)->create([
        'member_id' => $member->id, 'role' => 'Primary contact',
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->assertSee('Acme Contact')
        ->assertSee('Primary contact');
});

it('adds a participant via the member picker', function () {
    $member = Member::factory()->contact()->create(['name' => 'Pickable Person']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->set('memberSearch', 'Pickable')
        ->call('selectMember', $member->id)
        ->assertSet('memberId', $member->id)
        ->set('role', 'Account manager')
        ->call('add')
        ->assertSet('memberId', null)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('opportunity_participants', [
        'opportunity_id' => $this->opportunity->id,
        'member_id' => $member->id,
        'role' => 'Account manager',
    ]);
});

it('requires a member before adding', function () {
    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->set('role', 'Primary contact')
        ->call('add')
        ->assertHasErrors('memberId');

    expect($this->opportunity->participants()->count())->toBe(0);
});

it('toggles the mute flag', function () {
    $participant = OpportunityParticipant::factory()->for($this->opportunity)->create(['mute' => false]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->call('toggleMute', $participant->id);

    expect($participant->fresh()->mute)->toBeTrue();
});

it('updates a participant role inline', function () {
    $participant = OpportunityParticipant::factory()->for($this->opportunity)->create(['role' => 'Site contact']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->call('updateRole', $participant->id, 'Secondary contact');

    expect($participant->fresh()->role)->toBe('Secondary contact');
});

it('removes a participant', function () {
    $participant = OpportunityParticipant::factory()->for($this->opportunity)->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->call('remove', $participant->id);

    $this->assertDatabaseMissing('opportunity_participants', ['id' => $participant->id]);
});

it('forbids the page without the view permission', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->assertForbidden();
});

it('excludes already-attached members from the picker dropdown', function () {
    $attached = Member::factory()->contact()->create(['name' => 'Picker Attached']);
    $free = Member::factory()->contact()->create(['name' => 'Picker Free']);
    OpportunityParticipant::factory()->for($this->opportunity)->create(['member_id' => $attached->id]);

    $this->actingAs($this->owner);

    // The picker dropdown lists only unattached organisation/contact members.
    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->set('memberSearch', 'Picker')
        ->assertSee('Picker Free')
        ->assertDontSeeHtml('wire:click="selectMember('.$attached->id.')"');
});

it('only offers organisation and contact members in the picker', function () {
    $contact = Member::factory()->contact()->create(['name' => 'Eligible Contact']);
    $venue = Member::factory()->venue()->create(['name' => 'Ineligible Venue']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->set('memberSearch', 'Eligible')
        ->assertSee('Eligible Contact');

    Volt::test('opportunities.participants', ['opportunity' => $this->opportunity])
        ->set('memberSearch', 'Ineligible')
        ->assertDontSeeHtml('wire:click="selectMember('.$venue->id.')"');
});
