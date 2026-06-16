<?php

namespace App\Settings;

/**
 * Single Sign-On settings (group `sso`).
 *
 * These keys are persisted and read under the `sso` group — `settings('sso.*')`
 * splits on the first dot, so the group must be `sso` for the registry defaults,
 * rules, and types to resolve. (They were previously declared on
 * {@see IntegrationSettings} as `sso.*`-prefixed keys, which placed the defaults
 * under the wrong group and left `settings('sso.*')` with no registered default.)
 *
 * The Integrations settings page still surfaces these alongside the geocoding /
 * maps keys, but the persistence layer treats `sso` as its own group.
 */
class SsoSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'sso';
    }

    public function defaults(): array
    {
        return [
            'google_enabled' => false,
            'google_client_id' => '',
            'google_client_secret' => '',
            'microsoft_enabled' => false,
            'microsoft_client_id' => '',
            'microsoft_client_secret' => '',
            'allowed_email_domains' => [],
        ];
    }

    public function rules(): array
    {
        return [
            'google_enabled' => ['boolean'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'microsoft_enabled' => ['boolean'],
            'microsoft_client_id' => ['nullable', 'string', 'max:255'],
            'microsoft_client_secret' => ['nullable', 'string', 'max:255'],
            'allowed_email_domains' => ['array'],
            'allowed_email_domains.*' => ['string'],
        ];
    }

    public function types(): array
    {
        return [
            'google_enabled' => 'boolean',
            'google_client_id' => 'encrypted',
            'google_client_secret' => 'encrypted',
            'microsoft_enabled' => 'boolean',
            'microsoft_client_id' => 'encrypted',
            'microsoft_client_secret' => 'encrypted',
            // Policy data, not a credential — stored as plain JSON, not encrypted.
            'allowed_email_domains' => 'json',
        ];
    }
}
