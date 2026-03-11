<?php

use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;

it('has the correct fillable attributes', function () {
    $preference = new NotificationPreference;

    expect($preference->getFillable())->toBe([
        'user_id',
        'notification_type_id',
        'channels',
        'is_muted',
    ]);
});

it('casts channels to array', function () {
    $preference = NotificationPreference::factory()->create([
        'channels' => ['database', 'mail'],
    ]);

    $preference->refresh();
    expect($preference->channels)->toBe(['database', 'mail']);
    expect($preference->channels)->toBeArray();
});

it('casts is_muted to boolean', function () {
    $preference = NotificationPreference::factory()->create(['is_muted' => true]);
    $preference->refresh();

    expect($preference->is_muted)->toBeTrue();
    expect($preference->is_muted)->toBeBool();
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $preference = NotificationPreference::factory()->create(['user_id' => $user->id]);

    expect($preference->user)->toBeInstanceOf(User::class);
    expect($preference->user->id)->toBe($user->id);
});

it('belongs to a notification type', function () {
    $type = NotificationType::factory()->create();
    $preference = NotificationPreference::factory()->create(['notification_type_id' => $type->id]);

    expect($preference->notificationType)->toBeInstanceOf(NotificationType::class);
    expect($preference->notificationType->id)->toBe($type->id);
});

it('can be created as muted using factory state', function () {
    $preference = NotificationPreference::factory()->muted()->create();

    expect($preference->is_muted)->toBeTrue();
});
