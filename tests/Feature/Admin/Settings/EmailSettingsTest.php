<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the email settings page', function () {
    $this->get(route('admin.settings.email'))
        ->assertOk()
        ->assertSee('Email');
});

it('loads current settings with registry defaults', function () {
    Volt::test('admin.settings.email')
        ->assertSet('mailer', 'log')
        ->assertSet('smtpPort', 587)
        ->assertSet('smtpEncryption', 'tls')
        ->assertSet('sesRegion', 'eu-west-1');
});

it('loads stored settings overriding defaults', function () {
    settings()->setMany([
        'email.mailer' => 'smtp',
        'email.smtp_host' => 'mail.example.com',
        'email.from_address' => 'noreply@example.com',
    ]);

    Volt::test('admin.settings.email')
        ->assertSet('mailer', 'smtp')
        ->assertSet('smtpHost', 'mail.example.com')
        ->assertSet('fromAddress', 'noreply@example.com');
});

it('saves email settings', function () {
    Volt::test('admin.settings.email')
        ->set('mailer', 'smtp')
        ->set('smtpHost', 'smtp.example.com')
        ->set('smtpPort', 465)
        ->set('smtpUsername', 'user')
        ->set('smtpPassword', 'secret')
        ->set('smtpEncryption', 'ssl')
        ->set('fromAddress', 'noreply@example.com')
        ->set('fromName', 'Test App')
        ->set('replyToAddress', 'support@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('email-settings-saved');

    expect(settings('email.mailer'))->toBe('smtp');
    expect(settings('email.smtp_host'))->toBe('smtp.example.com');
    expect(settings('email.from_address'))->toBe('noreply@example.com');
    expect(settings('email.from_name'))->toBe('Test App');
});

it('validates mailer is required', function () {
    Volt::test('admin.settings.email')
        ->set('mailer', '')
        ->call('save')
        ->assertHasErrors(['mailer']);
});

it('validates mailer must be a valid option', function () {
    Volt::test('admin.settings.email')
        ->set('mailer', 'invalid')
        ->call('save')
        ->assertHasErrors(['mailer']);
});

it('validates from_address must be an email', function () {
    Volt::test('admin.settings.email')
        ->set('mailer', 'log')
        ->set('fromAddress', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['fromAddress']);
});

it('sends a test email', function () {
    Mail::fake();

    Volt::test('admin.settings.email')
        ->set('testEmailAddress', 'test@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors()
        ->assertDispatched('test-email-sent');

    Mail::assertSent(\App\Mail\TestEmail::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

it('validates test email address is required', function () {
    Volt::test('admin.settings.email')
        ->set('testEmailAddress', '')
        ->call('sendTestEmail')
        ->assertHasErrors(['testEmailAddress']);
});

it('validates test email address must be an email', function () {
    Volt::test('admin.settings.email')
        ->set('testEmailAddress', 'not-valid')
        ->call('sendTestEmail')
        ->assertHasErrors(['testEmailAddress']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.email'))
        ->assertForbidden();
});
