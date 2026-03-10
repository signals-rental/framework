<?php

use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

test('notifications page renders for admin users', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.notifications'))
        ->assertOk();
});

test('notifications page is forbidden for non-admin users', function () {
    $this->actingAs($this->user)
        ->get(route('admin.settings.notifications'))
        ->assertForbidden();
});

test('notifications page displays notification types grouped by category', function () {
    $usersType = NotificationType::factory()->create([
        'category' => 'Users',
        'name' => 'User Invited',
    ]);
    $systemType = NotificationType::factory()->create([
        'category' => 'System',
        'name' => 'Test Email',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.settings.notifications'))
        ->assertOk()
        ->assertSee('Users')
        ->assertSee('User Invited')
        ->assertSee('System')
        ->assertSee('Test Email');
});

test('owner can toggle a channel for a notification type', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.notifications')
        ->call('toggleChannel', $type->id, 'mail');

    $setting = NotificationSetting::where('notification_type_id', $type->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->channels)->not->toContain('mail');
    expect($setting->channels)->toContain('database');
});

test('toggling channel on adds it back', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    // First toggle off mail
    NotificationSetting::create([
        'notification_type_id' => $type->id,
        'channels' => ['database'],
        'is_enabled' => true,
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.notifications')
        ->call('toggleChannel', $type->id, 'mail');

    $setting = NotificationSetting::where('notification_type_id', $type->id)->first();
    expect($setting->channels)->toContain('mail');
    expect($setting->channels)->toContain('database');
});

test('owner can toggle notification type enabled/disabled', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database'],
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.notifications')
        ->call('toggleEnabled', $type->id);

    $setting = NotificationSetting::where('notification_type_id', $type->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->is_enabled)->toBeFalse();
});

test('toggling enabled back on re-enables the type', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database'],
    ]);

    NotificationSetting::create([
        'notification_type_id' => $type->id,
        'channels' => ['database'],
        'is_enabled' => false,
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.notifications')
        ->call('toggleEnabled', $type->id);

    $setting = NotificationSetting::where('notification_type_id', $type->id)->first();
    expect($setting->is_enabled)->toBeTrue();
});

test('non-owner admin cannot toggle channels without permission', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.settings.notifications')
        ->call('toggleChannel', $type->id, 'mail')
        ->assertForbidden();
});

test('non-owner admin cannot toggle enabled without permission', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database'],
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.settings.notifications')
        ->call('toggleEnabled', $type->id)
        ->assertForbidden();
});
