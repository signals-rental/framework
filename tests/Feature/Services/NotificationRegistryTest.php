<?php

use App\Services\NotificationRegistry;

beforeEach(function () {
    $this->registry = new NotificationRegistry;
});

test('can register and retrieve a notification type', function () {
    $this->registry->register('user.invited', [
        'category' => 'Users',
        'name' => 'User Invited',
        'description' => 'Sent when a new user is invited.',
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    expect($this->registry->has('user.invited'))->toBeTrue();
    expect($this->registry->get('user.invited'))
        ->toBeArray()
        ->toHaveKey('category', 'Users')
        ->toHaveKey('name', 'User Invited');
});

test('returns null for unregistered type', function () {
    expect($this->registry->get('nonexistent'))->toBeNull();
    expect($this->registry->has('nonexistent'))->toBeFalse();
});

test('can register many types at once', function () {
    $this->registry->registerMany([
        'user.invited' => [
            'category' => 'Users',
            'name' => 'User Invited',
            'description' => 'Invitation sent.',
            'available_channels' => ['database', 'mail'],
            'default_channels' => ['database'],
        ],
        'system.test' => [
            'category' => 'System',
            'name' => 'Test',
            'description' => 'Test notification.',
            'available_channels' => ['mail'],
            'default_channels' => ['mail'],
        ],
    ]);

    expect($this->registry->all())->toHaveCount(2);
});

test('groups types by category', function () {
    $this->registry->registerMany([
        'user.invited' => [
            'category' => 'Users',
            'name' => 'User Invited',
            'description' => 'Invitation.',
            'available_channels' => ['database', 'mail'],
            'default_channels' => ['database'],
        ],
        'user.deactivated' => [
            'category' => 'Users',
            'name' => 'User Deactivated',
            'description' => 'Deactivation.',
            'available_channels' => ['database'],
            'default_channels' => ['database'],
        ],
        'system.test' => [
            'category' => 'System',
            'name' => 'Test',
            'description' => 'Test.',
            'available_channels' => ['mail'],
            'default_channels' => ['mail'],
        ],
    ]);

    $grouped = $this->registry->grouped();

    expect($grouped)->toHaveCount(2);
    expect($grouped['Users'])->toHaveCount(2);
    expect($grouped['System'])->toHaveCount(1);
});

test('syncs types to database', function () {
    $this->registry->registerMany([
        'user.invited' => [
            'category' => 'Users',
            'name' => 'User Invited',
            'description' => 'Invitation.',
            'available_channels' => ['database', 'mail'],
            'default_channels' => ['database', 'mail'],
        ],
        'system.test' => [
            'category' => 'System',
            'name' => 'Test',
            'description' => 'Test notification.',
            'available_channels' => ['mail'],
            'default_channels' => ['mail'],
        ],
    ]);

    $this->registry->syncToDatabase();

    expect(\App\Models\NotificationType::count())->toBe(2);

    $type = \App\Models\NotificationType::where('key', 'user.invited')->first();
    expect($type->category)->toBe('Users');
    expect($type->name)->toBe('User Invited');
});

test('sync updates existing types without duplicating', function () {
    $this->registry->register('user.invited', [
        'category' => 'Users',
        'name' => 'User Invited',
        'description' => 'Original description.',
        'available_channels' => ['database'],
        'default_channels' => ['database'],
    ]);

    $this->registry->syncToDatabase();

    // Re-register with updated description
    $updatedRegistry = new NotificationRegistry;
    $updatedRegistry->register('user.invited', [
        'category' => 'Users',
        'name' => 'User Invited',
        'description' => 'Updated description.',
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database'],
    ]);

    $updatedRegistry->syncToDatabase();

    expect(\App\Models\NotificationType::count())->toBe(1);
    expect(\App\Models\NotificationType::where('key', 'user.invited')->first())
        ->description->toBe('Updated description.');
});
