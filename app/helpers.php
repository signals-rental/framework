<?php

use App\Services\SettingsService;

if (! function_exists('settings')) {
    /**
     * Get a setting value or the SettingsService instance.
     *
     * @param  string|null  $key  Dot-notation key (e.g. 'company.name')
     * @param  mixed  $default  Default value if not found
     * @return ($key is null ? SettingsService : mixed)
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}
