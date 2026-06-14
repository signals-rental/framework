<?php

use App\Services\Auth\SsoService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\GoogleProvider;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Tests\TestCase;

// Uses DatabaseMigrations because the service reads per-tenant credentials and
// toggles via settings(), which are persisted to the database.
uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    $this->sso = app(SsoService::class);
});

/**
 * Read a protected property off a built Socialite provider for assertions.
 */
function ssoProviderProperty(Provider $provider, string $property): mixed
{
    $reflection = new ReflectionObject($provider);

    while (! $reflection->hasProperty($property)) {
        $reflection = $reflection->getParentClass();

        if ($reflection === false) {
            throw new RuntimeException("Property [{$property}] not found.");
        }
    }

    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);

    return $prop->getValue($provider);
}

// ─── isCloud() ───────────────────────────────────────────────────

it('reflects the signals.cloud config flag', function () {
    config(['signals.cloud' => true]);
    expect($this->sso->isCloud())->toBeTrue();

    config(['signals.cloud' => false]);
    expect($this->sso->isCloud())->toBeFalse();
});

// ─── configured() ────────────────────────────────────────────────

it('reads cloud credentials from config services when on cloud', function () {
    config([
        'signals.cloud' => true,
        'services.google.client_id' => 'cloud-google-id',
        'services.google.client_secret' => 'cloud-google-secret',
    ]);

    expect($this->sso->configured('google'))->toBeTrue();
});

it('is not configured on cloud when config credentials are missing', function () {
    config([
        'signals.cloud' => true,
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    expect($this->sso->configured('google'))->toBeFalse();
});

it('reads self-hosted credentials from settings when not on cloud', function () {
    config(['signals.cloud' => false]);

    settings()->set('sso.google_client_id', 'self-hosted-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'self-hosted-secret', 'encrypted');

    expect($this->sso->configured('google'))->toBeTrue();
});

it('is not configured self-hosted when settings credentials are missing', function () {
    config(['signals.cloud' => false]);

    expect($this->sso->configured('google'))->toBeFalse();
});

it('is never configured for an unknown provider', function () {
    config(['signals.cloud' => true, 'services.github.client_id' => 'x', 'services.github.client_secret' => 'y']);

    expect($this->sso->configured('github'))->toBeFalse();
});

// ─── enabled() ───────────────────────────────────────────────────

it('reads the enable toggle from settings', function () {
    settings()->set('sso.google_enabled', true, 'boolean');
    expect($this->sso->enabled('google'))->toBeTrue();

    settings()->set('sso.google_enabled', false, 'boolean');
    expect($this->sso->enabled('google'))->toBeFalse();
});

it('is disabled when no enable toggle is stored', function () {
    expect($this->sso->enabled('microsoft'))->toBeFalse();
});

it('is never enabled for an unknown provider', function () {
    settings()->set('sso.github_enabled', true, 'boolean');
    expect($this->sso->enabled('github'))->toBeFalse();
});

// ─── enabledProviders() ──────────────────────────────────────────

it('lists only enabled and configured providers in display order', function () {
    config(['signals.cloud' => false]);

    // Google: enabled + configured.
    settings()->set('sso.google_enabled', true, 'boolean');
    settings()->set('sso.google_client_id', 'g-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'g-secret', 'encrypted');

    // Microsoft: enabled + configured.
    settings()->set('sso.microsoft_enabled', true, 'boolean');
    settings()->set('sso.microsoft_client_id', 'm-id', 'encrypted');
    settings()->set('sso.microsoft_client_secret', 'm-secret', 'encrypted');

    expect($this->sso->enabledProviders())->toBe(['google', 'microsoft']);
});

it('excludes providers that are enabled but not configured', function () {
    config(['signals.cloud' => false]);

    settings()->set('sso.google_enabled', true, 'boolean');
    // No google credentials stored.

    settings()->set('sso.microsoft_enabled', true, 'boolean');
    settings()->set('sso.microsoft_client_id', 'm-id', 'encrypted');
    settings()->set('sso.microsoft_client_secret', 'm-secret', 'encrypted');

    expect($this->sso->enabledProviders())->toBe(['microsoft']);
});

it('excludes providers that are configured but not enabled', function () {
    config(['signals.cloud' => false]);

    settings()->set('sso.google_client_id', 'g-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'g-secret', 'encrypted');
    // google_enabled not set.

    expect($this->sso->enabledProviders())->toBe([]);
});

it('returns an empty list when no providers are available', function () {
    expect($this->sso->enabledProviders())->toBe([]);
});

// ─── driver() ────────────────────────────────────────────────────

it('builds a google driver with the resolved client id and redirect', function () {
    config(['signals.cloud' => false]);

    settings()->set('sso.google_enabled', true, 'boolean');
    settings()->set('sso.google_client_id', 'g-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'g-secret', 'encrypted');

    $driver = $this->sso->driver('google');

    expect($driver)->toBeInstanceOf(Provider::class)
        ->and($driver)->toBeInstanceOf(GoogleProvider::class)
        ->and(ssoProviderProperty($driver, 'clientId'))->toBe('g-id')
        ->and(ssoProviderProperty($driver, 'redirectUrl'))->toEndWith('/auth/google/callback');
});

it('builds a microsoft driver carrying the tenant', function () {
    config(['signals.cloud' => false]);

    settings()->set('sso.microsoft_enabled', true, 'boolean');
    settings()->set('sso.microsoft_client_id', 'm-id', 'encrypted');
    settings()->set('sso.microsoft_client_secret', 'm-secret', 'encrypted');

    $driver = $this->sso->driver('microsoft');

    expect($driver)->toBeInstanceOf(Provider::class)
        ->and($driver)->toBeInstanceOf(MicrosoftProvider::class)
        ->and(ssoProviderProperty($driver, 'clientId'))->toBe('m-id')
        ->and(ssoProviderProperty($driver, 'redirectUrl'))->toEndWith('/auth/microsoft/callback');

    // The SocialiteProviders Config object stores the tenant in the provider's
    // config array; assert it is honoured for self-hosted (always 'common').
    expect(ssoProviderProperty($driver, 'config'))->toMatchArray(['tenant' => 'common']);
});

it('builds a cloud microsoft driver with the configured azure tenant', function () {
    config([
        'signals.cloud' => true,
        'services.microsoft.client_id' => 'cloud-m-id',
        'services.microsoft.client_secret' => 'cloud-m-secret',
        'services.microsoft.tenant' => 'my-azure-tenant',
    ]);
    settings()->set('sso.microsoft_enabled', true, 'boolean');

    $driver = $this->sso->driver('microsoft');

    expect(ssoProviderProperty($driver, 'config'))->toMatchArray(['tenant' => 'my-azure-tenant']);
});

it('does not mutate global services config when building a self-hosted driver', function () {
    config(['signals.cloud' => false, 'services.google.client_id' => null]);

    settings()->set('sso.google_enabled', true, 'boolean');
    settings()->set('sso.google_client_id', 'settings-only-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'settings-only-secret', 'encrypted');

    $this->sso->driver('google');

    // The driver was built from settings; the global services config must be untouched.
    expect(config('services.google.client_id'))->toBeNull();
});

it('throws for an unknown provider', function () {
    $this->sso->driver('github');
})->throws(InvalidArgumentException::class);

it('throws when the provider is enabled but not configured', function () {
    config(['signals.cloud' => false]);
    settings()->set('sso.google_enabled', true, 'boolean');
    // No credentials stored → unavailable.

    $this->sso->driver('google');
})->throws(InvalidArgumentException::class);

it('throws when the provider is configured but not enabled', function () {
    config(['signals.cloud' => false]);
    settings()->set('sso.google_client_id', 'g-id', 'encrypted');
    settings()->set('sso.google_client_secret', 'g-secret', 'encrypted');
    // google_enabled not set → unavailable.

    $this->sso->driver('google');
})->throws(InvalidArgumentException::class);
