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
            ->toHaveKey('group', 'Settings')
            ->toHaveKey('sub_group')
            ->toHaveKey('layer', 'action')
            ->toHaveKey('dependencies');
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

    it('includes access permissions for area-layer gating', function () {
        $registry = app(PermissionRegistry::class);

        expect($registry->has('settings.access'))->toBeTrue();
        expect($registry->has('members.access'))->toBeTrue();
        expect($registry->has('opportunities.access'))->toBeTrue();
        expect($registry->has('invoices.access'))->toBeTrue();
        expect($registry->has('products.access'))->toBeTrue();
        expect($registry->has('stock.access'))->toBeTrue();
        expect($registry->has('reports.access'))->toBeTrue();
        expect($registry->has('users.access'))->toBeTrue();
    });

    it('includes the costs.view field-level permission', function () {
        $registry = app(PermissionRegistry::class);
        $meta = $registry->get('costs.view');

        expect($meta)->not->toBeNull();
        expect($meta['layer'])->toBe('field');
        expect($meta['group'])->toBe('Global');
    });

    it('filters permissions by layer', function () {
        $registry = app(PermissionRegistry::class);

        $areaPermissions = $registry->byLayer('area');
        expect($areaPermissions)->not->toBeEmpty();
        foreach ($areaPermissions as $meta) {
            expect($meta['layer'])->toBe('area');
        }

        $fieldPermissions = $registry->byLayer('field');
        expect($fieldPermissions)->toHaveKey('costs.view');
    });

    it('resolves direct dependencies for a permission', function () {
        $registry = app(PermissionRegistry::class);

        $deps = $registry->dependenciesFor('settings.view');
        expect($deps)->toContain('settings.access');
    });

    it('resolves recursive dependencies', function () {
        $registry = app(PermissionRegistry::class);

        $deps = $registry->dependenciesFor('settings.manage');
        expect($deps)->toContain('settings.view');
        expect($deps)->toContain('settings.access');
    });

    it('returns empty array for permissions with no dependencies', function () {
        $registry = app(PermissionRegistry::class);

        $deps = $registry->dependenciesFor('settings.access');
        expect($deps)->toBe([]);
    });

    it('returns empty array for unknown permissions', function () {
        $registry = app(PermissionRegistry::class);

        $deps = $registry->dependenciesFor('nonexistent.permission');
        expect($deps)->toBe([]);
    });

    it('normalises metadata with defaults when sub_group/layer/dependencies omitted', function () {
        $registry = new PermissionRegistry;
        $registry->register('test.perm', [
            'label' => 'Test',
            'description' => 'Test permission',
            'group' => 'Testing',
        ]);

        $meta = $registry->get('test.perm');
        expect($meta['sub_group'])->toBeNull();
        expect($meta['layer'])->toBe('action');
        expect($meta['dependencies'])->toBe([]);
    });
});
