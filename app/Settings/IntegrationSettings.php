<?php

namespace App\Settings;

class IntegrationSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'integrations';
    }

    public function defaults(): array
    {
        return [
            'what3words_api_key' => '',
            'google_maps_api_key' => '',
            'sso.google_enabled' => false,
            'sso.google_client_id' => '',
            'sso.google_client_secret' => '',
            'sso.microsoft_enabled' => false,
            'sso.microsoft_client_id' => '',
            'sso.microsoft_client_secret' => '',
            'sso.allowed_email_domains' => [],
        ];
    }

    public function rules(): array
    {
        return [
            'what3words_api_key' => ['nullable', 'string', 'max:255'],
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
            'sso.google_enabled' => ['boolean'],
            'sso.google_client_id' => ['nullable', 'string', 'max:255'],
            'sso.google_client_secret' => ['nullable', 'string', 'max:255'],
            'sso.microsoft_enabled' => ['boolean'],
            'sso.microsoft_client_id' => ['nullable', 'string', 'max:255'],
            'sso.microsoft_client_secret' => ['nullable', 'string', 'max:255'],
            'sso.allowed_email_domains' => ['array'],
            'sso.allowed_email_domains.*' => ['string'],
        ];
    }

    public function types(): array
    {
        return [
            'what3words_api_key' => 'encrypted',
            'google_maps_api_key' => 'encrypted',
            'sso.google_enabled' => 'boolean',
            'sso.google_client_id' => 'encrypted',
            'sso.google_client_secret' => 'encrypted',
            'sso.microsoft_enabled' => 'boolean',
            'sso.microsoft_client_id' => 'encrypted',
            'sso.microsoft_client_secret' => 'encrypted',
            // Policy data, not a credential — stored as plain JSON, not encrypted.
            'sso.allowed_email_domains' => 'json',
        ];
    }
}
