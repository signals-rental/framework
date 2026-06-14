<?php

namespace App\Services\Auth;

use InvalidArgumentException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use SocialiteProviders\Manager\Config as SocialiteProvidersConfig;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

/**
 * Resolves Single Sign-On configuration and builds Socialite drivers per-request.
 *
 * The service is the single source of truth for which providers are enabled and
 * configured, and constructs Socialite drivers in an Octane-safe manner — it never
 * mutates the global `config('services.*')` repository at request time, which would
 * leak credentials across requests and (on Signals Cloud) across tenants.
 */
class SsoService
{
    /**
     * Supported SSO providers, in login-page display order.
     *
     * @var list<string>
     */
    public const PROVIDERS = ['google', 'microsoft'];

    /**
     * Determine whether the application is running on Signals Cloud.
     *
     * On Cloud, credentials come from env (`config('services.*')`); self-hosted
     * installs resolve credentials from encrypted settings at runtime.
     */
    public function isCloud(): bool
    {
        return (bool) config('signals.cloud');
    }

    /**
     * Determine whether the given provider's per-tenant enable toggle is on.
     *
     * Unknown providers are never enabled.
     */
    public function enabled(string $provider): bool
    {
        if (! $this->isSupported($provider)) {
            return false;
        }

        return (bool) settings("sso.{$provider}_enabled");
    }

    /**
     * Determine whether credentials are present for the given provider.
     *
     * On Cloud, credentials are read from `config('services.*')` (env). Self-hosted
     * installs read the encrypted `sso.{provider}_client_id`/`_client_secret` settings.
     */
    public function configured(string $provider): bool
    {
        if (! $this->isSupported($provider)) {
            return false;
        }

        if ($this->isCloud()) {
            return filled(config("services.{$provider}.client_id"))
                && filled(config("services.{$provider}.client_secret"));
        }

        return filled(settings("sso.{$provider}_client_id"))
            && filled(settings("sso.{$provider}_client_secret"));
    }

    /**
     * Determine whether the given provider is both enabled and configured.
     *
     * This is the condition that exposes a provider's login button.
     */
    public function available(string $provider): bool
    {
        return $this->enabled($provider) && $this->configured($provider);
    }

    /**
     * List the providers that are available (enabled and configured), in display order.
     *
     * @return list<string>
     */
    public function enabledProviders(): array
    {
        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider): bool => $this->available($provider),
        ));
    }

    /**
     * Build a configured Socialite driver for the given provider.
     *
     * The driver is constructed per-request with credentials resolved into a local
     * array — the global `config('services.*')` repository is never mutated, so the
     * build is safe under Octane and multi-tenancy.
     *
     * @throws InvalidArgumentException When the provider is unknown or unavailable.
     */
    public function driver(string $provider): Provider
    {
        if (! $this->available($provider)) {
            throw new InvalidArgumentException(
                "SSO provider [{$provider}] is not available (unknown, disabled, or unconfigured).",
            );
        }

        $config = $this->credentialsFor($provider);
        $socialite = app(SocialiteManager::class);

        return match ($provider) {
            'google' => $socialite->buildProvider(GoogleProvider::class, $config),
            'microsoft' => $this->buildMicrosoftDriver($socialite, $config),
            default => throw new InvalidArgumentException("SSO provider [{$provider}] is not supported."),
        };
    }

    /**
     * Build the Microsoft driver, applying the Azure tenant via the SocialiteProviders
     * Config object so it is honoured without mutating global configuration.
     *
     * @param  array{client_id: ?string, client_secret: ?string, redirect: string, tenant?: string}  $config
     */
    protected function buildMicrosoftDriver(SocialiteManager $socialite, array $config): Provider
    {
        /** @var MicrosoftProvider $driver */
        $driver = $socialite->buildProvider(MicrosoftProvider::class, $config);

        $driver->setConfig(new SocialiteProvidersConfig(
            $config['client_id'],
            $config['client_secret'],
            $config['redirect'],
            ['tenant' => $config['tenant'] ?? 'common'],
        ));

        return $driver;
    }

    /**
     * Resolve the credential array for the given provider.
     *
     * On Cloud, the env-backed `config('services.*')` array is used. Self-hosted
     * installs resolve credentials from encrypted settings. The redirect URL is
     * always computed locally so it reflects the current request host.
     *
     * @return array{client_id: ?string, client_secret: ?string, redirect: string, tenant?: string}
     */
    protected function credentialsFor(string $provider): array
    {
        $redirect = route('sso.callback', ['provider' => $provider]);

        if ($this->isCloud()) {
            $config = [
                'client_id' => config("services.{$provider}.client_id"),
                'client_secret' => config("services.{$provider}.client_secret"),
                'redirect' => $redirect,
            ];
        } else {
            $config = [
                'client_id' => settings("sso.{$provider}_client_id"),
                'client_secret' => settings("sso.{$provider}_client_secret"),
                'redirect' => $redirect,
            ];
        }

        // Microsoft (Azure) needs a tenant: Cloud reads the configured tenant from
        // env; self-hosted always uses the multitenant 'common' tenant.
        if ($provider === 'microsoft') {
            $config['tenant'] = $this->isCloud()
                ? (string) config('services.microsoft.tenant', 'common')
                : 'common';
        }

        return $config;
    }

    /**
     * Determine whether the given provider is in the supported allow-list.
     */
    protected function isSupported(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }
}
