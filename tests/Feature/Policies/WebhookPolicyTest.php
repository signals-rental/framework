<?php

use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('WebhookPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view webhooks', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', Webhook::class))->toBeTrue();
        });

        it('allows user with webhooks.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('webhooks.manage');

            expect($user->can('viewAny', Webhook::class))->toBeTrue();
        });

        it('denies user without webhooks.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', Webhook::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a webhook', function () {
            $owner = User::factory()->owner()->create();
            $webhook = Webhook::factory()->create();

            expect($owner->can('view', $webhook))->toBeTrue();
        });

        it('allows user with webhooks.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('webhooks.manage');
            $webhook = Webhook::factory()->create();

            expect($user->can('view', $webhook))->toBeTrue();
        });

        it('denies user without webhooks.manage permission', function () {
            $user = User::factory()->create();
            $webhook = Webhook::factory()->create();

            expect($user->can('view', $webhook))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create webhooks', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', Webhook::class))->toBeTrue();
        });

        it('allows user with webhooks.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('webhooks.manage');

            expect($user->can('create', Webhook::class))->toBeTrue();
        });

        it('denies user without webhooks.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', Webhook::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a webhook', function () {
            $owner = User::factory()->owner()->create();
            $webhook = Webhook::factory()->create();

            expect($owner->can('update', $webhook))->toBeTrue();
        });

        it('allows user with webhooks.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('webhooks.manage');
            $webhook = Webhook::factory()->create();

            expect($user->can('update', $webhook))->toBeTrue();
        });

        it('denies user without webhooks.manage permission', function () {
            $user = User::factory()->create();
            $webhook = Webhook::factory()->create();

            expect($user->can('update', $webhook))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a webhook', function () {
            $owner = User::factory()->owner()->create();
            $webhook = Webhook::factory()->create();

            expect($owner->can('delete', $webhook))->toBeTrue();
        });

        it('allows user with webhooks.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('webhooks.manage');
            $webhook = Webhook::factory()->create();

            expect($user->can('delete', $webhook))->toBeTrue();
        });

        it('denies user without webhooks.manage permission', function () {
            $user = User::factory()->create();
            $webhook = Webhook::factory()->create();

            expect($user->can('delete', $webhook))->toBeFalse();
        });
    });
});
