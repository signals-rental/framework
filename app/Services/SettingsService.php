<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * Get a setting value by dot-notation key (group.key) or all settings for a group.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadIfNeeded();

        if (! str_contains($key, '.')) {
            return $this->cache[$key] ?? $default;
        }

        [$group, $settingKey] = explode('.', $key, 2);

        if (! isset($this->cache[$group]) || ! is_array($this->cache[$group])) {
            return $default;
        }

        return array_key_exists($settingKey, $this->cache[$group])
            ? $this->cache[$group][$settingKey]
            : $default;
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        [$group, $settingKey] = explode('.', $key, 2);

        $storedValue = $this->encodeValue($value, $type);

        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            ['value' => $storedValue, 'type' => $type],
        );

        $this->flush();
    }

    /**
     * Set multiple settings at once.
     *
     * Accepts either simple values or arrays with 'value' and optional 'type' keys:
     *   ['company.name' => 'Signals']
     *   ['modules.crm' => ['value' => true, 'type' => 'boolean']]
     *
     * @param  array<string, mixed>  $settings
     */
    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $setting) {
            $value = is_array($setting) && array_key_exists('value', $setting) ? $setting['value'] : $setting;
            $type = is_array($setting) && array_key_exists('type', $setting) ? $setting['type'] : 'string';

            [$group, $settingKey] = explode('.', $key, 2);

            Setting::query()->updateOrCreate(
                ['group' => $group, 'key' => $settingKey],
                ['value' => $this->encodeValue($value, $type), 'type' => $type],
            );
        }

        $this->flush();
    }

    /**
     * Check whether a module is enabled.
     */
    public function moduleEnabled(string $module): bool
    {
        return (bool) $this->get("modules.{$module}", false);
    }

    /**
     * Check if a setting exists.
     */
    public function has(string $key): bool
    {
        $this->loadIfNeeded();

        if (! str_contains($key, '.')) {
            return isset($this->cache[$key]);
        }

        [$group, $settingKey] = explode('.', $key, 2);

        return isset($this->cache[$group]) && is_array($this->cache[$group]) && array_key_exists($settingKey, $this->cache[$group]);
    }

    /**
     * Get all settings as a nested array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->loadIfNeeded();

        return $this->cache ?? [];
    }

    /**
     * Load settings from cache or database.
     */
    public function load(): void
    {
        $this->cache = $this->loadFromCache();
    }

    /**
     * Flush the in-memory and external cache.
     */
    public function flush(): void
    {
        $this->cache = null;

        if ($this->supportsTags()) {
            Cache::tags(['settings'])->flush();
        } else {
            Cache::forget('settings:all');
        }
    }

    private function loadIfNeeded(): void
    {
        if ($this->cache === null) {
            $this->load();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadFromCache(): array
    {
        $callback = fn (): array => $this->loadFromDatabase();

        if ($this->supportsTags()) {
            return Cache::tags(['settings'])->rememberForever('settings:all', $callback);
        }

        return Cache::rememberForever('settings:all', $callback);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadFromDatabase(): array
    {
        $settings = Setting::all();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->group][$setting->key] = $this->decodeValue($setting->value, $setting->type);
        }

        return $result;
    }

    private function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) (int) $value,
            'json' => is_string($value) ? $value : json_encode($value),
            'encrypted' => Crypt::encryptString(is_string($value) ? $value : json_encode($value)),
            default => (string) $value,
        };
    }

    private function decodeValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value === 'true' || $value === '1',
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            'encrypted' => Crypt::decryptString($value),
            default => $value,
        };
    }

    private function supportsTags(): bool
    {
        try {
            return Cache::getStore() instanceof \Illuminate\Cache\TaggableStore;
        } catch (\Exception) {
            return false;
        }
    }
}
