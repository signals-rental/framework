<?php

use App\Actions\Activities\CompleteActivity;
use App\Enums\ActivityStatus;
use App\Events\AuditableEvent;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('marks an activity as completed', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create(['completed' => false]);

    $result = (new CompleteActivity)($activity);

    expect($result->completed)->toBeTrue();
    expect($result->status_id)->toBe(ActivityStatus::Completed->value);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'activity.completed';
    });
});

it('can complete an already-completed activity without error', function () {
    Event::fake([AuditableEvent::class]);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->completed()->create();

    $result = (new CompleteActivity)($activity);

    expect($result->completed)->toBeTrue();
    expect($result->status_id)->toBe(ActivityStatus::Completed->value);

    Event::assertDispatched(AuditableEvent::class);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new CompleteActivity)($activity);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);
