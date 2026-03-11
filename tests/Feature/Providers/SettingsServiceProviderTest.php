<?php

use App\Models\Setting;
use App\Services\SettingsRegistry;
use App\Services\SettingsService;

it('registers SettingsService as singleton', function () {
    $service1 = app(SettingsService::class);
    $service2 = app(SettingsService::class);

    expect($service1)->toBe($service2);
});

it('registers SettingsRegistry as singleton with all definitions', function () {
    $registry = app(SettingsRegistry::class);

    expect($registry)->toBeInstanceOf(SettingsRegistry::class);

    $all = $registry->all();
    $groups = array_map(fn ($def) => $def->group(), $all);

    expect($groups)->toContain('email');
    expect($groups)->toContain('security');
    expect($groups)->toContain('action-log');
    expect($groups)->toContain('api');
});

it('configures SMTP mail settings from stored values', function () {
    config(['signals.installed' => true]);

    // Seed settings for SMTP configuration
    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'smtp'],
        ['group' => 'email', 'key' => 'smtp_host', 'value' => 'mail.example.com'],
        ['group' => 'email', 'key' => 'smtp_port', 'value' => '465'],
        ['group' => 'email', 'key' => 'smtp_username', 'value' => 'user@example.com'],
        ['group' => 'email', 'key' => 'smtp_password', 'value' => 'secret'],
        ['group' => 'email', 'key' => 'smtp_encryption', 'value' => 'tls'],
        ['group' => 'email', 'key' => 'from_address', 'value' => 'noreply@example.com'],
        ['group' => 'email', 'key' => 'from_name', 'value' => 'Test App'],
        ['group' => 'email', 'key' => 'reply_to_address', 'value' => 'reply@example.com'],
    ], ['group', 'key'], ['value']);

    // Re-load settings and configure mail
    app(SettingsService::class)->load();

    // Manually trigger configureMailFromSettings by re-booting
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(config('mail.default'))->toBe('smtp');
    expect(config('mail.mailers.smtp.host'))->toBe('mail.example.com');
    expect(config('mail.from.address'))->toBe('noreply@example.com');
    expect(config('mail.from.name'))->toBe('Test App');
    expect(config('mail.reply_to.address'))->toBe('reply@example.com');
});

it('skips mail config when mailer is log', function () {
    config(['signals.installed' => true]);

    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'log'],
    ], ['group', 'key'], ['value']);

    $originalDefault = config('mail.default');

    app(SettingsService::class)->load();
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    // mail.default should not change to 'log' since the log mailer is skipped
    expect(config('mail.default'))->toBe($originalDefault);
});

it('handles missing settings table gracefully', function () {
    config(['signals.installed' => true]);

    // Drop the settings table to simulate first-run
    \Illuminate\Support\Facades\Schema::drop('settings');

    // Should not throw
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(true)->toBeTrue();
});

it('configures SES mail settings from stored values', function () {
    config(['signals.installed' => true]);

    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'ses'],
        ['group' => 'email', 'key' => 'ses_key', 'value' => 'AKIAEXAMPLE'],
        ['group' => 'email', 'key' => 'ses_secret', 'value' => 'ses-secret'],
        ['group' => 'email', 'key' => 'ses_region', 'value' => 'us-east-1'],
    ], ['group', 'key'], ['value']);

    app(SettingsService::class)->load();
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(config('mail.default'))->toBe('ses');
    expect(config('services.ses.key'))->toBe('AKIAEXAMPLE');
    expect(config('services.ses.secret'))->toBe('ses-secret');
    expect(config('services.ses.region'))->toBe('us-east-1');
});

it('configures Mailgun mail settings from stored values', function () {
    config(['signals.installed' => true]);

    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'mailgun'],
        ['group' => 'email', 'key' => 'mailgun_domain', 'value' => 'mg.example.com'],
        ['group' => 'email', 'key' => 'mailgun_secret', 'value' => 'mg-secret'],
    ], ['group', 'key'], ['value']);

    app(SettingsService::class)->load();
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(config('mail.default'))->toBe('mailgun');
    expect(config('services.mailgun.domain'))->toBe('mg.example.com');
    expect(config('services.mailgun.secret'))->toBe('mg-secret');
});

it('configures Postmark mail settings from stored values', function () {
    config(['signals.installed' => true]);

    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'postmark'],
        ['group' => 'email', 'key' => 'postmark_token', 'value' => 'pm-token'],
    ], ['group', 'key'], ['value']);

    app(SettingsService::class)->load();
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(config('mail.default'))->toBe('postmark');
    expect(config('services.postmark.token'))->toBe('pm-token');
});

it('logs warning for unknown mailer driver', function () {
    config(['signals.installed' => true]);

    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'unknown_driver'],
        ['group' => 'email', 'key' => 'from_address', 'value' => ''],
    ], ['group', 'key'], ['value']);

    app(SettingsService::class)->load();

    \Illuminate\Support\Facades\Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'unknown_driver'));

    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(config('mail.default'))->toBe('unknown_driver');
});

it('logs unexpected database errors during boot', function () {
    config(['signals.installed' => true]);

    // Mock SettingsService to throw a non-table-not-found QueryException
    $this->mock(SettingsService::class, function ($mock) {
        $mock->shouldReceive('load')->andThrow(
            new \Illuminate\Database\QueryException(
                'pgsql',
                'SELECT * FROM settings',
                [],
                new \Exception('connection refused')
            )
        );
    });

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'unexpected database error'));

    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();
});

it('registers scheduling and preferences definitions', function () {
    $registry = app(SettingsRegistry::class);
    $groups = array_map(fn ($def) => $def->group(), $registry->all());

    expect($groups)->toContain('scheduling');
    expect($groups)->toContain('preferences');
});

it('sets smtp encryption to null when set to none', function () {
    config(['signals.installed' => true]);

    Setting::query()->upsert([
        ['group' => 'email', 'key' => 'mailer', 'value' => 'smtp'],
        ['group' => 'email', 'key' => 'smtp_encryption', 'value' => 'none'],
    ], ['group', 'key'], ['value']);

    app(SettingsService::class)->load();
    $provider = new \App\Providers\SettingsServiceProvider(app());
    $provider->boot();

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});
