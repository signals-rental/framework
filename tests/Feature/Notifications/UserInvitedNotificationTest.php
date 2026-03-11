<?php

use App\Models\User;
use App\Notifications\UserInvitedNotification;

it('sends via mail channel', function () {
    $notification = new UserInvitedNotification;
    $user = User::factory()->invited()->create();

    expect($notification->via($user))->toBe(['mail']);
});

it('generates a mail message with signed URL', function () {
    $user = User::factory()->invited()->create();
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail->actionUrl)->toContain('signature=');
    expect($mail->actionUrl)->toContain('/invitation/');
});

it('includes the user name in the greeting', function () {
    $user = User::factory()->invited()->create(['name' => 'Jane Smith']);
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail->greeting)->toBe('Hello Jane Smith!');
});

it('mentions the expiry period in the message body', function () {
    $user = User::factory()->invited()->create();
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    $bodyText = implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines);
    expect($bodyText)->toContain('7 days');
});

it('includes the app name in the subject', function () {
    config(['app.name' => 'Signals']);
    $user = User::factory()->invited()->create();
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Signals');
});
