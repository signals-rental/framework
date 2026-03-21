<?php

use App\Actions\Activities\CompleteActivity;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('marks an activity as completed', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create(['completed' => false]);

    $result = (new CompleteActivity)($activity);

    expect($result->completed)->toBeTrue();
    expect($result->status_id)->toBe(ActivityStatus::Completed->value);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new CompleteActivity)($activity);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);
