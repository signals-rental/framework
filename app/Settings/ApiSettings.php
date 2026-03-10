<?php

namespace App\Settings;

class ApiSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'api';
    }

    public function defaults(): array
    {
        return [
            'rate_limit' => 60,
            'rate_limit_unauthenticated' => 20,
            'token_expiration_days' => 0,
        ];
    }

    public function rules(): array
    {
        return [
            'rate_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'rate_limit_unauthenticated' => ['required', 'integer', 'min:1', 'max:10000'],
            'token_expiration_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ];
    }

    public function types(): array
    {
        return [
            'rate_limit' => 'integer',
            'rate_limit_unauthenticated' => 'integer',
            'token_expiration_days' => 'integer',
        ];
    }
}
