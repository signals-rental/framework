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

describe('Activities icon gating on activities.access', function () {
    // The header renders Activities inside the CRM mega-dropdown's "Engagement"
    // column (gated on activities.access). route('activities.index') also appears in
    // the always-present command palette, so we assert against the Activities
    // mega-item itself, not the bare URL.
    $activitiesNav = 'mega-item-label">Activities';

    it('shows the activities icon to a user with only activities.access', function () use ($activitiesNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['activities.access', 'activities.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($activitiesNav, false)
            // The People & Places column is gated separately on members.access — none leak in.
            ->assertDontSee('People &amp; Places', false)
            ->assertDontSee(route('members.index'), false);
    });

    it('shows the activities icon to a member-only user when they also hold activities.access', function () use ($activitiesNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['members.access', 'members.view', 'activities.access']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('People &amp; Places', false)
            ->assertSee($activitiesNav, false);
    });

    it('hides the activities icon from a member-only user without activities.access', function () use ($activitiesNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['members.access', 'members.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('People &amp; Places', false)
            ->assertDontSee($activitiesNav, false);
    });

    it('hides both the CRM dropdown and the activities icon from a user with neither permission', function () use ($activitiesNav) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('People &amp; Places', false)
            ->assertDontSee(route('members.index'), false)
            ->assertDontSee($activitiesNav, false);
    });
});

describe('Restructured navigation group labels', function () {
    it('renders the new top-level mega-dropdown group labels', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Job Planning', false)
            ->assertSee('Finance', false)
            ->assertSee('Operations', false)
            ->assertSee('Catalogue', false)
            ->assertSee('Services', false);
    });

    it('renders the new Job Planning, Warehouse, Purchasing and Reports nav entries', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // Job Planning → Opportunities additions
            ->assertSee('Planner', false)
            ->assertSee('Equipment Availability', false)
            // Operations → Warehouse subsection (rendered first)
            ->assertSee('Warehouse', false)
            ->assertSee('Processing', false)
            ->assertSee('Prep Bays', false)
            ->assertSee('Shelf', false)
            ->assertSee('Global Check-in', false)
            ->assertSee('Containers', false)
            // Finance → Purchasing subsection
            ->assertSee('Purchasing', false)
            ->assertSee('Purchase Orders', false)
            // New top-level module
            ->assertSee('Reports', false)
            // Resources → Services addition
            ->assertSee('Vehicles', false);
    });

    it('renders the extensions menu items', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Workflows', false)
            ->assertSee('Plugin', false)
            ->assertSee('API', false);
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
