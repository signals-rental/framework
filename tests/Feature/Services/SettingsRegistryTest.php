<?php

use App\Services\SettingsRegistry;
use App\Services\SettingsService;
use App\Settings\EmailSettings;
use App\Settings\SecuritySettings;

describe('SettingsRegistry', function () {
    it('registers and retrieves a settings definition', function () {
        $registry = new SettingsRegistry;
        $definition = new EmailSettings;

        $registry->register($definition);

        expect($registry->get('email'))->toBe($definition);
    });

    it('returns null for unregistered groups', function () {
        $registry = new SettingsRegistry;

        expect($registry->get('nonexistent'))->toBeNull();
    });

    it('checks if a group is registered', function () {
        $registry = new SettingsRegistry;
        $registry->register(new EmailSettings);

        expect($registry->has('email'))->toBeTrue();
        expect($registry->has('nonexistent'))->toBeFalse();
    });

    it('returns all registered definitions', function () {
        $registry = new SettingsRegistry;
        $registry->register(new EmailSettings);
        $registry->register(new SecuritySettings);

        $all = $registry->all();

        expect($all)->toHaveCount(2)
            ->toHaveKey('email')
            ->toHaveKey('security');
    });

    it('returns defaults for a registered group', function () {
        $registry = new SettingsRegistry;
        $registry->register(new EmailSettings);

        $defaults = $registry->defaults('email');

        expect($defaults)->toHaveKey('mailer', 'log');
    });

    it('returns empty array for unregistered group defaults', function () {
        $registry = new SettingsRegistry;

        expect($registry->defaults('nonexistent'))->toBe([]);
    });

    it('returns types for a registered group', function () {
        $registry = new SettingsRegistry;
        $registry->register(new EmailSettings);

        $types = $registry->types('email');

        expect($types)->toHaveKey('smtp_password', 'encrypted');
    });

    it('returns validation rules for a registered group', function () {
        $registry = new SettingsRegistry;
        $registry->register(new SecuritySettings);

        $rules = $registry->rules('security');

        expect($rules)->toHaveKey('password_min_length');
    });
});

describe('SettingsRegistry is registered in the container', function () {
    it('resolves as a singleton from the container', function () {
        $registry = app(SettingsRegistry::class);

        expect($registry)->toBeInstanceOf(SettingsRegistry::class);
        expect($registry->has('email'))->toBeTrue();
        expect($registry->has('security'))->toBeTrue();
    });
});

describe('SettingsService integration with registry', function () {
    it('falls back to registry defaults for unset settings', function () {
        $service = app(SettingsService::class);

        expect($service->get('email.mailer'))->toBe('log');
        expect($service->get('email.smtp_port'))->toBe(587);
        expect($service->get('security.password_min_length'))->toBe(8);
        expect($service->get('security.max_login_attempts'))->toBe(5);
    });

    it('returns explicit default when neither stored nor in registry', function () {
        $service = app(SettingsService::class);

        expect($service->get('email.nonexistent', 'fallback'))->toBe('fallback');
        expect($service->get('unknown.key', 'default'))->toBe('default');
    });

    it('prefers stored values over registry defaults', function () {
        $service = app(SettingsService::class);

        $service->set('email.mailer', 'smtp');

        expect($service->get('email.mailer'))->toBe('smtp');
    });

    it('returns merged group with defaults and stored values', function () {
        $service = app(SettingsService::class);

        $service->set('email.mailer', 'smtp');
        $service->set('email.smtp_host', 'mail.example.com');

        $group = $service->group('email');

        // Stored values override defaults
        expect($group['mailer'])->toBe('smtp');
        expect($group['smtp_host'])->toBe('mail.example.com');
        // Defaults fill in unstored values
        expect($group['smtp_port'])->toBe(587);
        expect($group['smtp_encryption'])->toBe('tls');
        expect($group['from_address'])->toBe('');
    });

    it('returns empty group from database merged with defaults', function () {
        $service = app(SettingsService::class);

        $group = $service->group('security');

        expect($group['password_min_length'])->toBe(8);
        expect($group['require_2fa_admin'])->toBe(false);
    });

    it('returns empty array for groups with no registry and no stored data', function () {
        $service = app(SettingsService::class);

        expect($service->group('nonexistent'))->toBe([]);
    });
});
