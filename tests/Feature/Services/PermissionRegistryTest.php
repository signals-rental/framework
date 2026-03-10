<?php

use App\Services\PermissionRegistry;

describe('PermissionRegistry', function () {
    it('is registered in the container', function () {
        $registry = app(PermissionRegistry::class);

        expect($registry)->toBeInstanceOf(PermissionRegistry::class);
    });

    it('contains all permission metadata', function () {
        $registry = app(PermissionRegistry::class);

        expect($registry->has('settings.view'))->toBeTrue();
        expect($registry->has('users.invite'))->toBeTrue();
        expect($registry->has('opportunities.create'))->toBeTrue();
    });

    it('returns metadata for a permission', function () {
        $registry = app(PermissionRegistry::class);
        $meta = $registry->get('settings.view');

        expect($meta)
            ->toHaveKey('label', 'View Settings')
            ->toHaveKey('description')
            ->toHaveKey('group', 'Settings');
    });

    it('returns null for unregistered permissions', function () {
        $registry = app(PermissionRegistry::class);

        expect($registry->get('nonexistent.permission'))->toBeNull();
    });

    it('groups permissions by group name', function () {
        $registry = app(PermissionRegistry::class);
        $grouped = $registry->grouped();

        expect($grouped)->toHaveKey('Settings');
        expect($grouped)->toHaveKey('Users');
        expect($grouped)->toHaveKey('Opportunities');
        expect($grouped['Settings'])->toHaveKey('settings.view');
    });

    it('returns all permission keys', function () {
        $registry = app(PermissionRegistry::class);
        $keys = $registry->keys();

        expect($keys)->toContain('settings.view');
        expect($keys)->toContain('webhooks.manage');
    });
});
