<?php

use App\Mail\TemplatedEmail;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Notifications\Messages\MailMessage;

it('sends via mail channel', function () {
    $notification = new UserInvitedNotification;
    $user = User::factory()->invited()->create();

    expect($notification->via($user))->toBe(['mail']);
});

it('renders the branded template with a signed invitation url', function () {
    (new EmailTemplateSeeder)->run();

    $user = User::factory()->invited()->create(['name' => 'Jane Smith']);
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(TemplatedEmail::class);

    if (! $mail instanceof TemplatedEmail) {
        $this->fail('Expected a TemplatedEmail mailable.');
    }

    expect($mail->bodyHtml)->toContain('Jane Smith');
    expect($mail->bodyHtml)->toContain('sig-btn');
    expect($mail->bodyHtml)->toContain('signature=');
    expect($mail->bodyHtml)->toContain('/invitation/');
});

it('includes the company name in the subject', function () {
    (new EmailTemplateSeeder)->run();

    $user = User::factory()->invited()->create();
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(TemplatedEmail::class);

    if (! $mail instanceof TemplatedEmail) {
        $this->fail('Expected a TemplatedEmail mailable.');
    }

    expect($mail->subjectLine)->toContain(settings('company.name', config('app.name')));
});

it('falls back to a mail message when the template is inactive', function () {
    (new EmailTemplateSeeder)->run();
    EmailTemplate::where('key', 'user_invited')->update(['is_active' => false]);

    $user = User::factory()->invited()->create(['name' => 'Jane Smith']);
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);
    expect($mail->greeting)->toBe('Hello Jane Smith!');
    expect($mail->actionUrl)->toContain('signature=');
    expect(implode(' ', $mail->outroLines))->toContain('7 days');
});

it('falls back to a mail message when the template is missing', function () {
    $user = User::factory()->invited()->create();
    $notification = new UserInvitedNotification;

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);
});
