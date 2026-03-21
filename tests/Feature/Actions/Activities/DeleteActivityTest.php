<?php

use App\Actions\Activities\DeleteActivity;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('deletes an activity', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new DeleteActivity)($activity);

    expect(Activity::find($activity->id))->toBeNull();
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $activity = Activity::factory()->create();

    (new DeleteActivity)($activity);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);
