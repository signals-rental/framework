<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('completes an activity and dispatches event', function () {
    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create(['completed' => false]);

    Volt::test('members.activities', ['member' => $member])
        ->call('completeActivity', $activity->id)
        ->assertDispatched('activity-completed')
        ->assertOk();

    expect($activity->refresh()->completed)->toBeTrue();
});

it('deletes an activity and dispatches event', function () {
    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('deleteActivity', $activity->id)
        ->assertDispatched('activity-deleted')
        ->assertOk();

    expect(Activity::find($activity->id))->toBeNull();
});

it('handles completing a non-existent activity gracefully', function () {
    $member = Member::factory()->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('completeActivity', 99999)
        ->assertOk();
});

it('handles deleting a non-existent activity gracefully', function () {
    $member = Member::factory()->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('deleteActivity', 99999)
        ->assertOk();
});

it('handles auth denial on complete gracefully', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create(['completed' => false]);

    Volt::test('members.activities', ['member' => $member])
        ->call('completeActivity', $activity->id)
        ->assertOk(); // Auth exception caught, no 403

    expect($activity->refresh()->completed)->toBeFalse();
});

it('handles auth denial on delete gracefully', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('deleteActivity', $activity->id)
        ->assertOk(); // Auth exception caught, no 403

    expect(Activity::find($activity->id))->not->toBeNull();
});
