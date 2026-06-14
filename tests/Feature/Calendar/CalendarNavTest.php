<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

describe('Calendar nav gating on activities.access', function () {
    it('shows the main Calendar nav link to a user with activities.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['activities.access', 'activities.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // Top-bar calendar icon + mobile sidebar link to the calendar page.
            ->assertSee(route('calendar.index'), false)
            // Command-palette Navigation entry.
            ->assertSee("label: 'Calendar'", false);
    });

    it('hides the main Calendar nav link from a user without activities.access', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('calendar.index'), false)
            ->assertDontSee("label: 'Calendar'", false);
    });

    it('keeps the Calendar page link distinct from the personal Calendar Feed settings entry', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['activities.access', 'activities.view']);

        // The settings feed entry/link is always present; the calendar page link is gated.
        // They must not be the same URL — calendar.index is the scheduling page,
        // settings.calendar is the per-user iCal feed settings page.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('calendar.index'), false)
            ->assertSee(route('settings.calendar'), false)
            ->assertSee("label: 'Calendar Feed'", false);

        expect(route('calendar.index'))->not->toBe(route('settings.calendar'));
    });
});
