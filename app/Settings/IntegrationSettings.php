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
        ];
    }

    public function rules(): array
    {
        return [
            'what3words_api_key' => ['nullable', 'string', 'max:255'],
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function types(): array
    {
        return [
            'what3words_api_key' => 'encrypted',
            'google_maps_api_key' => 'encrypted',
        ];
    }
}
