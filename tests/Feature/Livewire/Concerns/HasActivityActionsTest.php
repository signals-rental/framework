<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Log;
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
        ->assertDispatched('toast', type: 'success', message: 'Activity was already removed.')
        ->assertOk();
});

it('handles deleting a non-existent activity gracefully', function () {
    $member = Member::factory()->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('deleteActivity', 99999)
        ->assertDispatched('toast', type: 'success', message: 'Activity was already removed.')
        ->assertOk();
});

it('handles auth denial on complete gracefully', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create(['completed' => false]);

    Volt::test('members.activities', ['member' => $member])
        ->call('completeActivity', $activity->id)
        ->assertDispatched('toast', type: 'error', message: 'You do not have permission to complete this activity.')
        ->assertOk();

    expect($activity->refresh()->completed)->toBeFalse();
});

it('handles auth denial on delete gracefully', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('deleteActivity', $activity->id)
        ->assertDispatched('toast', type: 'error', message: 'You do not have permission to delete this activity.')
        ->assertOk();

    expect(Activity::find($activity->id))->not->toBeNull();
});

it('logs and toasts when completing an activity hits an unexpected error', function () {
    $log = Log::spy();

    $this->mock(WebhookService::class, function ($mock): void {
        $mock->shouldReceive('dispatch')->andThrow(new RuntimeException('Webhook down'));
    });

    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create(['completed' => false]);

    Volt::test('members.activities', ['member' => $member])
        ->call('completeActivity', $activity->id)
        ->assertDispatched('toast', type: 'error', message: 'Something went wrong. Please try again.');

    $log->shouldHaveReceived('error')
        ->once()
        ->with('Failed to complete activity', Mockery::on(fn (array $context): bool => $context['activity_id'] === $activity->id));

    expect($activity->refresh()->completed)->toBeFalse();
});

it('logs and toasts when deleting an activity hits an unexpected error', function () {
    $log = Log::spy();

    $this->mock(WebhookService::class, function ($mock): void {
        $mock->shouldReceive('dispatch')->andThrow(new RuntimeException('Webhook down'));
    });

    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('members.activities', ['member' => $member])
        ->call('deleteActivity', $activity->id)
        ->assertDispatched('toast', type: 'error', message: 'Something went wrong. Please try again.');

    $log->shouldHaveReceived('error')
        ->once()
        ->with('Failed to delete activity', Mockery::on(fn (array $context): bool => $context['activity_id'] === $activity->id));

    expect(Activity::find($activity->id))->not->toBeNull();
});
