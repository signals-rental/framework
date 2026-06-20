<?php

use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/*
|--------------------------------------------------------------------------
| Member → related Opportunities datatable (B5)
|--------------------------------------------------------------------------
|
| The member Show page exposes an "Opportunities" tab that lists every
| opportunity associated with the member in any CRM role — as the customer
| (member_id), the owning salesperson (owned_by), or a named participant.
| Rows for unrelated members must never leak in, the tab is gated on the
| `opportunities.access` area permission, and an unrelated member sees the
| empty state.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('lists opportunities where the member is the customer', function () {
    $member = Member::factory()->organisation()->create();
    Opportunity::factory()->create(['member_id' => $member->id, 'subject' => 'Customer Job']);

    $this->get("/members/{$member->id}/opportunities")
        ->assertOk()
        ->assertSee('Customer Job');
});

it('includes opportunities the member owns or participates in', function () {
    $member = Member::factory()->create();

    Opportunity::factory()->create(['owned_by' => $member->id, 'subject' => 'Owned Job']);

    $participated = Opportunity::factory()->create(['subject' => 'Participated Job']);
    OpportunityParticipant::factory()->create([
        'opportunity_id' => $participated->id,
        'member_id' => $member->id,
    ]);

    $this->get("/members/{$member->id}/opportunities")
        ->assertOk()
        ->assertSee('Owned Job')
        ->assertSee('Participated Job');
});

it("does not show another member's opportunities", function () {
    $member = Member::factory()->organisation()->create();
    $other = Member::factory()->organisation()->create();

    Opportunity::factory()->create(['member_id' => $member->id, 'subject' => 'Mine Job']);
    Opportunity::factory()->create(['member_id' => $other->id, 'subject' => 'Theirs Job']);

    $this->get("/members/{$member->id}/opportunities")
        ->assertOk()
        ->assertSee('Mine Job')
        ->assertDontSee('Theirs Job');
});

it('shows the empty state for a member with no opportunities', function () {
    $member = Member::factory()->organisation()->create();

    $this->get("/members/{$member->id}/opportunities")
        ->assertOk()
        ->assertSee('No opportunities for this member yet.');
});

it('forbids a user lacking opportunities.access', function () {
    $member = Member::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get("/members/{$member->id}/opportunities")
        ->assertForbidden();
});
