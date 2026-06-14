<?php

use Livewire\Volt\Volt as LivewireVolt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

/**
 * Enable and configure an SSO provider via self-hosted (settings-backed) credentials.
 */
function configureSsoProvider(string $provider): void
{
    settings()->set("sso.{$provider}_enabled", true, 'boolean');
    settings()->set("sso.{$provider}_client_id", "{$provider}-id", 'encrypted');
    settings()->set("sso.{$provider}_client_secret", "{$provider}-secret", 'encrypted');
}

it('renders a Continue with Google control linking to the google redirect route', function () {
    configureSsoProvider('google');

    LivewireVolt::test('auth.login')
        ->assertSee('Continue with Google')
        ->assertSee(route('sso.redirect', ['provider' => 'google']), escape: false);
});

it('renders both provider buttons in display order when both are configured', function () {
    configureSsoProvider('google');
    configureSsoProvider('microsoft');

    $rendered = (string) LivewireVolt::test('auth.login')->html();

    $googlePosition = strpos($rendered, 'Continue with Google');
    $microsoftPosition = strpos($rendered, 'Continue with Microsoft');

    expect($googlePosition)->not->toBeFalse()
        ->and($microsoftPosition)->not->toBeFalse()
        ->and($googlePosition)->toBeLessThan($microsoftPosition);

    LivewireVolt::test('auth.login')
        ->assertSee(route('sso.redirect', ['provider' => 'google']), escape: false)
        ->assertSee(route('sso.redirect', ['provider' => 'microsoft']), escape: false);
});

it('renders neither buttons nor the divider when no provider is enabled', function () {
    LivewireVolt::test('auth.login')
        ->assertDontSee('Continue with Google')
        ->assertDontSee('Continue with Microsoft')
        ->assertDontSee(route('sso.redirect', ['provider' => 'google']), escape: false)
        ->assertDontSee(route('sso.redirect', ['provider' => 'microsoft']), escape: false)
        ->assertDontSee('role="separator"', escape: false);
});

it('does not render a provider that is enabled but not configured', function () {
    settings()->set('sso.google_enabled', true, 'boolean');

    LivewireVolt::test('auth.login')
        ->assertDontSee('Continue with Google')
        ->assertDontSee(route('sso.redirect', ['provider' => 'google']), escape: false)
        ->assertDontSee('role="separator"', escape: false);
});
