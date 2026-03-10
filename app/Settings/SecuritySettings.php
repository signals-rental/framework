<?php

namespace App\Settings;

class SecuritySettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'security';
    }

    public function defaults(): array
    {
        return [
            'password_min_length' => 8,
            'password_require_uppercase' => false,
            'password_require_number' => false,
            'password_require_special' => false,
            'session_timeout' => 120,
            'max_login_attempts' => 5,
            'lockout_duration' => 15,
            'require_2fa_admin' => false,
            'require_2fa_all' => false,
        ];
    }

    public function rules(): array
    {
        return [
            'password_min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'password_require_uppercase' => ['required', 'boolean'],
            'password_require_number' => ['required', 'boolean'],
            'password_require_special' => ['required', 'boolean'],
            'session_timeout' => ['required', 'integer', 'min:5', 'max:1440'],
            'max_login_attempts' => ['required', 'integer', 'min:1', 'max:100'],
            'lockout_duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'require_2fa_admin' => ['required', 'boolean'],
            'require_2fa_all' => ['required', 'boolean'],
        ];
    }

    public function types(): array
    {
        return [
            'password_min_length' => 'integer',
            'password_require_uppercase' => 'boolean',
            'password_require_number' => 'boolean',
            'password_require_special' => 'boolean',
            'session_timeout' => 'integer',
            'max_login_attempts' => 'integer',
            'lockout_duration' => 'integer',
            'require_2fa_admin' => 'boolean',
            'require_2fa_all' => 'boolean',
        ];
    }
}
