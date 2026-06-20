<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Equipment Availability nav gating (M8-4b)
|--------------------------------------------------------------------------
|
| The Job Planning mega-dropdown and the mobile "Job Planning" sidebar both
| render the Equipment Availability link gated on availability.view, pointing at
| route('availability.index') with wire:navigate. route('availability.index')
| appears nowhere else in the chrome, so it is a clean gate marker.
|
| WRITTEN under the M8 cadence — NOT executed here.
|
*/

$availabilityNav = 'Stock availability &amp; conflicts';

describe('Equipment Availability nav gating on availability.view', function () use ($availabilityNav) {
    it('shows the Equipment Availability link to a user with availability.view', function () use ($availabilityNav) {
        $user = User::factory()->create();
        // availability.view depends on stock.access in the permission registry.
        $user->givePermissionTo(['stock.access', 'availability.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('availability.index'), false)
            ->assertSee($availabilityNav, false);
    });

    it('hides the Equipment Availability link from a user without availability.view', function () use ($availabilityNav) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('availability.index'), false)
            ->assertDontSee($availabilityNav, false);
    });
});
