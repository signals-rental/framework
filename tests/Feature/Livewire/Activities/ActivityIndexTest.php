<?php

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('renders the activities index page', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('Activities');
});

it('displays activities in the data table', function () {
    $user = User::factory()->owner()->create();
    Activity::factory()->create(['subject' => 'Test Activity']);

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('Test Activity');
});

it('shows the new activity button', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('New Activity');
});

// ── Bulk actions ──────────────────────────────────────────────────────────────

it('wires a bulk-actions view into the data table', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertSee('livewire.activities.partials.bulk-actions', false);
});

it('bulk-completes the selected activities', function () {
    $user = User::factory()->owner()->create();
    $a = Activity::factory()->create(['completed' => false, 'status_id' => ActivityStatus::Scheduled]);
    $b = Activity::factory()->create(['completed' => false, 'status_id' => ActivityStatus::Scheduled]);

    Volt::actingAs($user)
        ->test('activities.index')
        ->call('bulkComplete', [$a->id, $b->id]);

    expect($a->fresh()->completed)->toBeTrue();
    expect($b->fresh()->completed)->toBeTrue();
    expect($a->fresh()->status_id)->toBe(ActivityStatus::Completed);
});

it('bulk-deletes the selected activities', function () {
    $user = User::factory()->owner()->create();
    $a = Activity::factory()->create();
    $b = Activity::factory()->create();
    $keep = Activity::factory()->create();

    Volt::actingAs($user)
        ->test('activities.index')
        ->call('bulkDelete', [$a->id, $b->id]);

    expect(Activity::find($a->id))->toBeNull();
    expect(Activity::find($b->id))->toBeNull();
    expect(Activity::find($keep->id))->not->toBeNull();
});

it('skips already-completed activities on bulk complete', function () {
    $user = User::factory()->owner()->create();
    $done = Activity::factory()->completed()->create();

    Volt::actingAs($user)
        ->test('activities.index')
        ->call('bulkComplete', [$done->id]);

    expect($done->fresh()->completed)->toBeTrue();
});
