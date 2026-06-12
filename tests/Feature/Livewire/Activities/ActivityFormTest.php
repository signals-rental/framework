<?php

use App\Models\Activity;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('renders the create activity form', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities/create')
        ->assertOk()
        ->assertSee('Create Activity');
});

it('renders the edit activity form', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create(['subject' => 'Edit Me']);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}/edit")
        ->assertOk()
        ->assertSee('Edit Me');
});

it('renders the Schedule section before the Basic Info section', function () {
    $user = User::factory()->owner()->create();

    $html = $this->actingAs($user)
        ->get('/activities/create')
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'Schedule'))->toBeLessThan(strpos($html, 'Basic Info'));
});

it('saves an activity with a "Y-m-d H:i" datetime from the datetime-input binding', function () {
    $user = User::factory()->owner()->create();

    Volt::actingAs($user)
        ->test('activities.form')
        ->set('subject', 'Scheduled Task')
        ->set('startsAt', '2026-03-08 14:30')
        ->set('endsAt', '2026-03-08 16:00')
        ->call('save')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('subject', 'Scheduled Task')->firstOrFail();

    expect(Carbon::parse($activity->starts_at)->format('Y-m-d H:i'))->toBe('2026-03-08 14:30')
        ->and(Carbon::parse($activity->ends_at)->format('Y-m-d H:i'))->toBe('2026-03-08 16:00');
});

it('hydrates an existing activity datetime into the property in "Y-m-d H:i" format', function () {
    $user = User::factory()->owner()->create();
    $activity = Activity::factory()->create([
        'starts_at' => '2026-03-08 14:30:00',
        'ends_at' => '2026-03-08 16:00:00',
    ]);

    Volt::actingAs($user)
        ->test('activities.form', ['activity' => $activity])
        ->assertSet('startsAt', '2026-03-08 14:30')
        ->assertSet('endsAt', '2026-03-08 16:00');
});
