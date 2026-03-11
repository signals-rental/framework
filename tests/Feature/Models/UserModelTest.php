<?php

use App\Models\User;

describe('hasTwoFactorEnabled', function () {
    it('returns true when both secret and recovery codes are set', function () {
        $user = User::factory()->withTwoFactor()->create();

        expect($user->hasTwoFactorEnabled())->toBeTrue();
    });

    it('returns false when two factor is not configured', function () {
        $user = User::factory()->create();

        expect($user->hasTwoFactorEnabled())->toBeFalse();
    });

    it('returns true when decryption fails to force re-setup', function () {
        $user = User::factory()->create();

        // Set raw, unencryptable values directly in the database to trigger DecryptException
        $user->getConnection()->table('users')
            ->where('id', $user->id)
            ->update([
                'two_factor_secret' => 'corrupted-data-not-encrypted',
                'two_factor_recovery_codes' => 'corrupted-data-not-encrypted',
            ]);

        $user->refresh();

        expect($user->hasTwoFactorEnabled())->toBeTrue();
    });
});

describe('initials', function () {
    it('returns initials for a single word name', function () {
        $user = User::factory()->create(['name' => 'Alice']);

        expect($user->initials())->toBe('A');
    });

    it('returns initials for a multi-word name', function () {
        $user = User::factory()->create(['name' => 'Alice Bob']);

        expect($user->initials())->toBe('AB');
    });

    it('only takes first two words', function () {
        $user = User::factory()->create(['name' => 'Alice Bob Charlie']);

        expect($user->initials())->toBe('AB');
    });
});

describe('isActive', function () {
    it('returns true for active user', function () {
        $user = User::factory()->create(['is_active' => true]);

        expect($user->isActive())->toBeTrue();
    });

    it('returns false for deactivated user', function () {
        $user = User::factory()->deactivated()->create();

        expect($user->isActive())->toBeFalse();
    });
});

describe('hasAdminAccess', function () {
    it('returns true for owner', function () {
        $user = User::factory()->owner()->create();

        expect($user->hasAdminAccess())->toBeTrue();
    });

    it('returns true for admin', function () {
        $user = User::factory()->admin()->create();

        expect($user->hasAdminAccess())->toBeTrue();
    });

    it('returns true for user with Admin role', function () {
        // Create the Admin role for this test
        \Spatie\Permission\Models\Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        expect($user->hasAdminAccess())->toBeTrue();
    });

    it('returns false for regular user', function () {
        $user = User::factory()->create();

        expect($user->hasAdminAccess())->toBeFalse();
    });
});

describe('isOwner', function () {
    it('returns true for owner', function () {
        $user = User::factory()->owner()->create();

        expect($user->isOwner())->toBeTrue();
    });

    it('returns false for regular user', function () {
        $user = User::factory()->create();

        expect($user->isOwner())->toBeFalse();
    });
});

describe('generateRecoveryCodes', function () {
    it('generates 8 recovery codes in correct format', function () {
        $user = User::factory()->create();
        $codes = $user->generateRecoveryCodes();

        expect($codes)->toHaveCount(8);

        foreach ($codes as $code) {
            expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
        }
    });
});
