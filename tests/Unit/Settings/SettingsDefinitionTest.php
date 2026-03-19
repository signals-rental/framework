<?php

use App\Settings\ActionLogSettings;
use App\Settings\ApiSettings;
use App\Settings\EmailSettings;
use App\Settings\GeneralPreferencesSettings;
use App\Settings\IntegrationSettings;
use App\Settings\SchedulingSettings;
use App\Settings\SecuritySettings;
use App\Settings\SettingsDefinition;

describe('SettingsDefinition (abstract base)', function () {
    it('returns empty array from base types() method', function () {
        $definition = new class extends SettingsDefinition
        {
            public function group(): string
            {
                return 'test';
            }

            public function defaults(): array
            {
                return [];
            }

            public function rules(): array
            {
                return [];
            }
        };

        expect($definition->types())->toBe([]);
    });
});

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

describe('ActionLogSettings', function () {
    it('returns action-log as the group name', function () {
        $definition = new ActionLogSettings;

        expect($definition->group())->toBe('action-log');
    });

    it('provides defaults for all action log settings', function () {
        $definition = new ActionLogSettings;
        $defaults = $definition->defaults();

        expect($defaults)->toHaveKey('retention_months', 12);
    });

    it('provides validation rules for retention_months', function () {
        $definition = new ActionLogSettings;
        $rules = $definition->rules();

        expect($rules)->toHaveKey('retention_months');
        expect($rules['retention_months'])->toContain('required');
        expect($rules['retention_months'])->toContain('integer');
    });

    it('declares integer type for retention_months', function () {
        $definition = new ActionLogSettings;

        expect($definition->types())->toHaveKey('retention_months', 'integer');
    });
});

describe('ApiSettings', function () {
    it('returns api as the group name', function () {
        $definition = new ApiSettings;

        expect($definition->group())->toBe('api');
    });

    it('provides defaults for all api settings', function () {
        $definition = new ApiSettings;
        $defaults = $definition->defaults();

        expect($defaults)
            ->toHaveKey('rate_limit', 60)
            ->toHaveKey('rate_limit_unauthenticated', 20)
            ->toHaveKey('token_expiration_days', 0);
    });

    it('provides validation rules for all settings', function () {
        $definition = new ApiSettings;
        $rules = $definition->rules();

        expect($rules)
            ->toHaveKey('rate_limit')
            ->toHaveKey('rate_limit_unauthenticated')
            ->toHaveKey('token_expiration_days');

        expect($rules['rate_limit'])->toContain('required');
    });

    it('declares integer types for all settings', function () {
        $definition = new ApiSettings;
        $types = $definition->types();

        expect($types)
            ->toHaveKey('rate_limit', 'integer')
            ->toHaveKey('rate_limit_unauthenticated', 'integer')
            ->toHaveKey('token_expiration_days', 'integer');
    });
});

describe('SchedulingSettings', function () {
    it('returns scheduling as the group name', function () {
        $definition = new SchedulingSettings;

        expect($definition->group())->toBe('scheduling');
    });

    it('provides defaults for all scheduling settings', function () {
        $definition = new SchedulingSettings;
        $defaults = $definition->defaults();

        expect($defaults)
            ->toHaveKey('default_opportunity_duration_days', 1)
            ->toHaveKey('default_buffer_before_minutes', 0)
            ->toHaveKey('default_buffer_after_minutes', 0)
            ->toHaveKey('collection_reminder_days', 1)
            ->toHaveKey('return_reminder_days', 1)
            ->toHaveKey('default_start_time', '09:00')
            ->toHaveKey('default_end_time', '17:00')
            ->toHaveKey('weekend_availability', false);
    });

    it('provides validation rules for all settings', function () {
        $definition = new SchedulingSettings;
        $rules = $definition->rules();

        expect($rules)
            ->toHaveKey('default_opportunity_duration_days')
            ->toHaveKey('default_buffer_before_minutes')
            ->toHaveKey('default_start_time')
            ->toHaveKey('weekend_availability');

        expect($rules['default_opportunity_duration_days'])->toContain('required');
    });

    it('declares integer and boolean types', function () {
        $definition = new SchedulingSettings;
        $types = $definition->types();

        expect($types)
            ->toHaveKey('default_opportunity_duration_days', 'integer')
            ->toHaveKey('default_buffer_before_minutes', 'integer')
            ->toHaveKey('default_buffer_after_minutes', 'integer')
            ->toHaveKey('collection_reminder_days', 'integer')
            ->toHaveKey('return_reminder_days', 'integer')
            ->toHaveKey('weekend_availability', 'boolean');
    });
});

describe('IntegrationSettings', function () {
    it('returns integrations as the group name', function () {
        $definition = new IntegrationSettings;

        expect($definition->group())->toBe('integrations');
    });

    it('provides defaults for all integration settings', function () {
        $definition = new IntegrationSettings;
        $defaults = $definition->defaults();

        expect($defaults)
            ->toHaveKey('what3words_api_key', '')
            ->toHaveKey('google_maps_api_key', '');
    });

    it('provides validation rules for all settings', function () {
        $definition = new IntegrationSettings;
        $rules = $definition->rules();

        expect($rules)
            ->toHaveKey('what3words_api_key')
            ->toHaveKey('google_maps_api_key');
    });

    it('declares encrypted types for api key fields', function () {
        $definition = new IntegrationSettings;
        $types = $definition->types();

        expect($types)
            ->toHaveKey('what3words_api_key', 'encrypted')
            ->toHaveKey('google_maps_api_key', 'encrypted');
    });
});

describe('GeneralPreferencesSettings', function () {
    it('returns preferences as the group name', function () {
        $definition = new GeneralPreferencesSettings;

        expect($definition->group())->toBe('preferences');
    });

    it('provides defaults for all preference settings', function () {
        $definition = new GeneralPreferencesSettings;
        $defaults = $definition->defaults();

        expect($defaults)
            ->toHaveKey('number_decimal_separator', '.')
            ->toHaveKey('number_thousands_separator', ',')
            ->toHaveKey('currency_display', 'symbol')
            ->toHaveKey('first_day_of_week', 1)
            ->toHaveKey('items_per_page', 25);
    });

    it('provides validation rules for all settings', function () {
        $definition = new GeneralPreferencesSettings;
        $rules = $definition->rules();

        expect($rules)
            ->toHaveKey('number_decimal_separator')
            ->toHaveKey('number_thousands_separator')
            ->toHaveKey('currency_display')
            ->toHaveKey('first_day_of_week')
            ->toHaveKey('items_per_page');

        expect($rules['first_day_of_week'])->toContain('required');
    });

    it('declares integer types', function () {
        $definition = new GeneralPreferencesSettings;
        $types = $definition->types();

        expect($types)
            ->toHaveKey('first_day_of_week', 'integer')
            ->toHaveKey('items_per_page', 'integer');
    });
});
