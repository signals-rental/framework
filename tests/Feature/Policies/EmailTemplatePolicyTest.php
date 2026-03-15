<?php

use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('EmailTemplatePolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view email templates', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', EmailTemplate::class))->toBeTrue();
        });

        it('allows user with email-templates.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('email-templates.manage');

            expect($user->can('viewAny', EmailTemplate::class))->toBeTrue();
        });

        it('denies user without email-templates.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', EmailTemplate::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view an email template', function () {
            $owner = User::factory()->owner()->create();
            $template = EmailTemplate::factory()->create();

            expect($owner->can('view', $template))->toBeTrue();
        });

        it('allows user with email-templates.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('email-templates.manage');
            $template = EmailTemplate::factory()->create();

            expect($user->can('view', $template))->toBeTrue();
        });

        it('denies user without email-templates.manage permission', function () {
            $user = User::factory()->create();
            $template = EmailTemplate::factory()->create();

            expect($user->can('view', $template))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create email templates', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', EmailTemplate::class))->toBeTrue();
        });

        it('allows user with email-templates.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('email-templates.manage');

            expect($user->can('create', EmailTemplate::class))->toBeTrue();
        });

        it('denies user without email-templates.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', EmailTemplate::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update an email template', function () {
            $owner = User::factory()->owner()->create();
            $template = EmailTemplate::factory()->create();

            expect($owner->can('update', $template))->toBeTrue();
        });

        it('allows user with email-templates.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('email-templates.manage');
            $template = EmailTemplate::factory()->create();

            expect($user->can('update', $template))->toBeTrue();
        });

        it('denies user without email-templates.manage permission', function () {
            $user = User::factory()->create();
            $template = EmailTemplate::factory()->create();

            expect($user->can('update', $template))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete an email template', function () {
            $owner = User::factory()->owner()->create();
            $template = EmailTemplate::factory()->create();

            expect($owner->can('delete', $template))->toBeTrue();
        });

        it('allows user with email-templates.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('email-templates.manage');
            $template = EmailTemplate::factory()->create();

            expect($user->can('delete', $template))->toBeTrue();
        });

        it('denies user without email-templates.manage permission', function () {
            $user = User::factory()->create();
            $template = EmailTemplate::factory()->create();

            expect($user->can('delete', $template))->toBeFalse();
        });
    });
});
