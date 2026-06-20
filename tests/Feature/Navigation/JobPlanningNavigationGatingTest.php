<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Job Planning (Opportunities)
|--------------------------------------------------------------------------
|
| The Job Planning mega-dropdown and the mobile "Job Planning" sidebar
| section both render a live Opportunities link gated on opportunities.access
| (the Planner / Equipment Availability / Projects entries remain ungated
| placeholders until those modules ship).
|
| route('opportunities.index') also appears in the always-present command
| palette gated on the SAME opportunities.access permission, so the deny-path
| URL assertion stays valid. To be unambiguous, the nav link is also asserted
| against its mega-item description marker, which occurs only inside the gated
| Job Planning dropdown column.
|
*/

$opportunitiesNav = 'Quotes, orders &amp; hires';

describe('Opportunities nav gating on opportunities.access', function () use ($opportunitiesNav) {
    it('shows the live Opportunities nav link to a user with opportunities.access', function () use ($opportunitiesNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.access', 'opportunities.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('opportunities.index'), false)
            ->assertSee($opportunitiesNav, false);
    });

    it('hides the Opportunities nav link from a user without opportunities.access', function () use ($opportunitiesNav) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // route('opportunities.index') only appears in nav + palette, both
            // gated on opportunities.access, so it must be entirely absent here.
            ->assertDontSee(route('opportunities.index'), false)
            ->assertDontSee($opportunitiesNav, false);
    });
});

/*
|--------------------------------------------------------------------------
| Command palette "New Opportunity" create entry (gated on opportunities.create)
|--------------------------------------------------------------------------
|
| route('opportunities.create') appears ONLY in the command palette's gated
| Create entry, so it is a clean marker for the opportunities.create gate.
|
*/

describe('New Opportunity command-palette entry gating on opportunities.create', function () {
    it('shows the New Opportunity create entry to a user with opportunities.create', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.access', 'opportunities.create']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('opportunities.create'), false);
    });

    it('hides the New Opportunity create entry from a user without opportunities.create', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.access', 'opportunities.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('opportunities.create'), false);
    });
});
