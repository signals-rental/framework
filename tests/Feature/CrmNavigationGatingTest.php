<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

describe('CRM nav gating on members.access', function () {
    it('shows the CRM nav and member entries to a user with members.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['members.access', 'members.view', 'members.create']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('People &amp; Places', false)
            ->assertSee(route('members.index'), false)
            ->assertSee(route('members.create'), false);
    });

    it('hides the CRM nav and member entries from a user without members.access', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('People &amp; Places', false)
            ->assertDontSee(route('members.index'), false)
            ->assertDontSee(route('members.create'), false);
    });
});

describe('CRM nav gating per column', function () {
    it('shows the Engagement column and Activities link but not the People & Places column to a user with only activities.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['activities.access', 'activities.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Engagement', false)
            ->assertSee(route('activities.index'), false)
            ->assertDontSee('People &amp; Places', false)
            ->assertDontSee(route('members.index'), false);
    });

    it('shows the People & Places column but not the Engagement column to a user with only members.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['members.access', 'members.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('People &amp; Places', false)
            ->assertSee(route('members.index'), false)
            ->assertDontSee('Engagement', false);
    });

    it('hides the CRM dropdown entirely from a user with neither members.access nor activities.access', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('People &amp; Places', false)
            ->assertDontSee('Engagement', false)
            ->assertDontSee(route('members.index'), false);
    });
});

describe('Command palette member entries', function () {
    it('renders the New Member create command for a user with members.create', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['members.access', 'members.view', 'members.create']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("label: 'New Member'", false)
            ->assertSee("label: 'Members'", false);
    });

    it('omits the member commands for a user without members permissions', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("label: 'New Member'", false)
            ->assertDontSee("label: 'Members'", false);
    });
});

describe('Dashboard "Add Member" quick action', function () {
    it('links the quick action to the member create route for a permitted user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['members.access', 'members.view', 'members.create']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Add Member')
            ->assertSee(route('members.create'), false);
    });

    it('hides the Add Member quick action from a user without members.create', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Add Member');
    });
});
