<?php

use App\Models\ActionLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('ActionLogPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view action logs', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', ActionLog::class))->toBeTrue();
        });

        it('allows user with action-log.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('action-log.view');

            expect($user->can('viewAny', ActionLog::class))->toBeTrue();
        });

        it('denies user without action-log.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', ActionLog::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view an action log', function () {
            $owner = User::factory()->owner()->create();
            $actionLog = ActionLog::factory()->create();

            expect($owner->can('view', $actionLog))->toBeTrue();
        });

        it('allows user with action-log.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('action-log.view');
            $actionLog = ActionLog::factory()->create();

            expect($user->can('view', $actionLog))->toBeTrue();
        });

        it('denies user without action-log.view permission', function () {
            $user = User::factory()->create();
            $actionLog = ActionLog::factory()->create();

            expect($user->can('view', $actionLog))->toBeFalse();
        });
    });
});
