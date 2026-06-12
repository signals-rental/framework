<?php

use App\Actions\Admin\SendTestEmail;
use App\Mail\TemplatedEmail;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('sends a branded test email to the given address', function () {
    (new EmailTemplateSeeder)->run();
    Mail::fake();

    (new SendTestEmail)('recipient@example.com');

    Mail::assertSent(TemplatedEmail::class, function ($mail) {
        return $mail->hasTo('recipient@example.com');
    });
});

it('falls back to default content when the test_email template is missing', function () {
    // No EmailTemplateSeeder run.
    Mail::fake();

    (new SendTestEmail)('recipient@example.com');

    Mail::assertSent(TemplatedEmail::class, function ($mail) {
        return $mail->hasTo('recipient@example.com')
            && str_contains($mail->bodyHtml, 'verify your email configuration');
    });
});

it('throws an exception if mail delivery fails', function () {
    (new EmailTemplateSeeder)->run();

    Mail::shouldReceive('to->send')
        ->andThrow(new Exception('Connection refused'));

    (new SendTestEmail)('test@example.com');
})->throws(Exception::class, 'Connection refused');

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    Mail::fake();

    (new SendTestEmail)('test@example.com');
})->throws(AuthorizationException::class);
