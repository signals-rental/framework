<?php

use App\Settings\EmailSettings;
use App\Settings\SecuritySettings;

describe('EmailSettings', function () {
    it('returns email as the group name', function () {
        $definition = new EmailSettings;

        expect($definition->group())->toBe('email');
    });

    it('provides defaults for all email settings', function () {
        $definition = new EmailSettings;
        $defaults = $definition->defaults();

        expect($defaults)
            ->toHaveKey('mailer', 'log')
            ->toHaveKey('smtp_host', '')
            ->toHaveKey('smtp_port', 587)
            ->toHaveKey('smtp_encryption', 'tls')
            ->toHaveKey('from_address', '')
            ->toHaveKey('from_name', '')
            ->toHaveKey('reply_to_address', '');
    });

    it('provides validation rules for all settings', function () {
        $definition = new EmailSettings;
        $rules = $definition->rules();

        expect($rules)
            ->toHaveKey('mailer')
            ->toHaveKey('smtp_host')
            ->toHaveKey('from_address');

        expect($rules['mailer'])->toContain('required');
    });

    it('declares encrypted types for sensitive fields', function () {
        $definition = new EmailSettings;
        $types = $definition->types();

        expect($types)
            ->toHaveKey('smtp_password', 'encrypted')
            ->toHaveKey('ses_secret', 'encrypted')
            ->toHaveKey('mailgun_secret', 'encrypted')
            ->toHaveKey('postmark_token', 'encrypted');
    });

    it('declares integer type for smtp_port', function () {
        $definition = new EmailSettings;

        expect($definition->types())->toHaveKey('smtp_port', 'integer');
    });
});

describe('SecuritySettings', function () {
    it('returns security as the group name', function () {
        $definition = new SecuritySettings;

        expect($definition->group())->toBe('security');
    });

    it('provides sensible defaults', function () {
        $definition = new SecuritySettings;
        $defaults = $definition->defaults();

        expect($defaults)
            ->toHaveKey('password_min_length', 8)
            ->toHaveKey('password_require_uppercase', false)
            ->toHaveKey('password_require_number', false)
            ->toHaveKey('password_require_special', false)
            ->toHaveKey('session_timeout', 120)
            ->toHaveKey('max_login_attempts', 5)
            ->toHaveKey('lockout_duration', 15)
            ->toHaveKey('require_2fa_admin', false)
            ->toHaveKey('require_2fa_all', false);
    });

    it('declares boolean and integer types', function () {
        $definition = new SecuritySettings;
        $types = $definition->types();

        expect($types)
            ->toHaveKey('password_min_length', 'integer')
            ->toHaveKey('password_require_uppercase', 'boolean')
            ->toHaveKey('session_timeout', 'integer')
            ->toHaveKey('require_2fa_admin', 'boolean');
    });

    it('provides validation rules for all settings', function () {
        $definition = new SecuritySettings;
        $rules = $definition->rules();

        expect($rules)->toHaveKey('password_min_length');
        expect($rules['password_min_length'])->toContain('required');
    });
});
