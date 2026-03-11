<?php

use App\Models\NotificationSetting;
use App\Models\NotificationType;

it('casts channels to array', function () {
    $type = NotificationType::factory()->create();
    $setting = NotificationSetting::factory()->create([
        'notification_type_id' => $type->id,
        'channels' => ['mail', 'database'],
    ]);

    expect($setting->channels)->toBe(['mail', 'database']);
});

it('casts is_enabled to boolean', function () {
    $type = NotificationType::factory()->create();
    $setting = NotificationSetting::factory()->create([
        'notification_type_id' => $type->id,
        'is_enabled' => true,
    ]);

    expect($setting->is_enabled)->toBeTrue();
});

it('belongs to a notification type', function () {
    $type = NotificationType::factory()->create();
    $setting = NotificationSetting::factory()->create([
        'notification_type_id' => $type->id,
    ]);

    expect($setting->notificationType)->toBeInstanceOf(NotificationType::class);
    expect($setting->notificationType->id)->toBe($type->id);
});
