<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the integrations settings page', function () {
    $this->get(route('admin.settings.integrations'))
        ->assertOk()
        ->assertSee('Integrations')
        ->assertSee('Single Sign-On');
});

it('loads sso settings with defaults', function () {
    Volt::test('admin.settings.integrations')
        ->assertSet('ssoGoogleEnabled', false)
        ->assertSet('ssoGoogleClientId', '')
        ->assertSet('ssoGoogleClientSecret', '')
        ->assertSet('ssoMicrosoftEnabled', false)
        ->assertSet('ssoMicrosoftClientId', '')
        ->assertSet('ssoMicrosoftClientSecret', '');
});

it('saves sso credentials and toggles on self-hosted', function () {
    config(['signals.cloud' => false]);

    Volt::test('admin.settings.integrations')
        ->set('ssoGoogleEnabled', true)
        ->set('ssoGoogleClientId', 'google-client-id')
        ->set('ssoGoogleClientSecret', 'google-secret')
        ->set('ssoMicrosoftEnabled', true)
        ->set('ssoMicrosoftClientId', 'ms-client-id')
        ->set('ssoMicrosoftClientSecret', 'ms-secret')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('integration-settings-saved');

    expect(settings('sso.google_enabled'))->toBeTrue();
    expect(settings('sso.google_client_id'))->toBe('google-client-id');
    expect(settings('sso.google_client_secret'))->toBe('google-secret');
    expect(settings('sso.microsoft_enabled'))->toBeTrue();
    expect(settings('sso.microsoft_client_id'))->toBe('ms-client-id');
    expect(settings('sso.microsoft_client_secret'))->toBe('ms-secret');
});

it('stores sso client secrets encrypted at rest', function () {
    config(['signals.cloud' => false]);

    Volt::test('admin.settings.integrations')
        ->set('ssoGoogleEnabled', true)
        ->set('ssoGoogleClientId', 'google-client-id')
        ->set('ssoGoogleClientSecret', 'super-secret-value')
        ->call('save')
        ->assertHasNoErrors();

    $stored = Setting::query()
        ->where('group', 'sso')
        ->where('key', 'google_client_secret')
        ->value('value');

    expect($stored)->not->toBe('super-secret-value');
    expect(Crypt::decryptString($stored))->toBe('super-secret-value');
});

it('shows sso key fields on self-hosted installs', function () {
    config(['signals.cloud' => false]);

    Volt::test('admin.settings.integrations')
        ->assertSee('Enable Google sign-in')
        ->assertSee('Google Client ID')
        ->assertSee('Google Client Secret')
        ->assertSee('Microsoft Client ID')
        ->assertSee('Microsoft Client Secret')
        ->assertSee('Redirect / Callback URL');
});

it('hides sso key fields but keeps toggles on cloud', function () {
    config(['signals.cloud' => true]);

    Volt::test('admin.settings.integrations')
        ->assertSee('Enable Google sign-in')
        ->assertSee('Enable Microsoft 365 sign-in')
        ->assertSee('managed by Signals Cloud')
        ->assertDontSee('Google Client ID')
        ->assertDontSee('Google Client Secret')
        ->assertDontSee('Microsoft Client ID')
        ->assertDontSee('Microsoft Client Secret')
        ->assertDontSee('Redirect / Callback URL');
});

it('saves sso toggles only and ignores key fields on cloud', function () {
    config(['signals.cloud' => true]);

    Volt::test('admin.settings.integrations')
        ->set('ssoGoogleEnabled', true)
        ->set('ssoMicrosoftEnabled', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('integration-settings-saved');

    expect(settings('sso.google_enabled'))->toBeTrue();
    expect(settings('sso.microsoft_enabled'))->toBeFalse();
    // Credential fields are never written on cloud (managed via env), so nothing is
    // persisted — the read falls back to the registered SsoSettings default ('').
    expect(settings('sso.google_client_id'))->toBe('');
    expect(Setting::query()->where('group', 'sso')->where('key', 'google_client_id')->exists())->toBeFalse();
});

// ─── allowed email domains ───────────────────────────────────────

it('saves and round-trips the allowed email domains, normalising the input', function () {
    config(['signals.cloud' => false]);

    Volt::test('admin.settings.integrations')
        ->set('ssoAllowedEmailDomains', "Owned.Example, second.example\nOWNED.example  third.example")
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('integration-settings-saved')
        // Re-rendered, de-duplicated, lowercased, newline-joined.
        ->assertSet('ssoAllowedEmailDomains', "owned.example\nsecond.example\nthird.example");

    expect(settings('sso.allowed_email_domains'))->toBe(['owned.example', 'second.example', 'third.example']);
});

it('loads stored allowed email domains as newline-separated text', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example', 'second.example'], 'json');

    Volt::test('admin.settings.integrations')
        ->assertSet('ssoAllowedEmailDomains', "owned.example\nsecond.example");
});

it('renders only valid string entries when the stored allow-list is mixed-type', function () {
    // The stored accessor must drop non-string / blank entries so a corrupt value
    // cannot break the textarea round-trip.
    settings()->set('sso.allowed_email_domains', [1, null, 'valid.example', '  ', 'second.example'], 'json');

    Volt::test('admin.settings.integrations')
        ->assertSet('ssoAllowedEmailDomains', "valid.example\nsecond.example");
});

it('persists the allowed email domains on cloud too (policy, not a credential)', function () {
    config(['signals.cloud' => true]);

    Volt::test('admin.settings.integrations')
        ->set('ssoAllowedEmailDomains', 'owned.example')
        ->call('save')
        ->assertHasNoErrors();

    expect(settings('sso.allowed_email_domains'))->toBe(['owned.example']);
});

// ─── write-only client secrets ───────────────────────────────────

it('does not load stored client secrets into the component (write-only)', function () {
    settings()->set('sso.google_client_secret', 'stored-google-secret', 'encrypted');
    settings()->set('sso.microsoft_client_secret', 'stored-ms-secret', 'encrypted');

    Volt::test('admin.settings.integrations')
        ->assertSet('ssoGoogleClientSecret', '')
        ->assertSet('ssoMicrosoftClientSecret', '');
});

it('does not render a stored client secret into the page markup', function () {
    config(['signals.cloud' => false]);
    settings()->set('sso.google_client_secret', 'top-secret-google', 'encrypted');

    Volt::test('admin.settings.integrations')
        ->assertDontSee('top-secret-google')
        ->assertSee('Configured — leave blank to keep');
});

it('keeps the existing secret when the secret field is submitted blank', function () {
    config(['signals.cloud' => false]);
    settings()->set('sso.google_client_secret', 'original-secret', 'encrypted');
    settings()->set('sso.google_client_id', 'gid', 'encrypted');

    Volt::test('admin.settings.integrations')
        ->set('ssoGoogleClientId', 'gid-updated')
        ->set('ssoGoogleClientSecret', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(settings('sso.google_client_secret'))->toBe('original-secret');
    expect(settings('sso.google_client_id'))->toBe('gid-updated');
});

it('updates the secret when a new value is entered', function () {
    config(['signals.cloud' => false]);
    settings()->set('sso.google_client_secret', 'original-secret', 'encrypted');

    Volt::test('admin.settings.integrations')
        ->set('ssoGoogleClientSecret', 'rotated-secret')
        ->call('save')
        ->assertHasNoErrors()
        // Write-only input is cleared after save.
        ->assertSet('ssoGoogleClientSecret', '');

    expect(settings('sso.google_client_secret'))->toBe('rotated-secret');
});

it('renders the callback url via the named sso.callback route', function () {
    config(['signals.cloud' => false]);

    Volt::test('admin.settings.integrations')
        ->assertSee(route('sso.callback', ['provider' => 'google']), escape: false)
        ->assertSee(route('sso.callback', ['provider' => 'microsoft']), escape: false);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.integrations'))
        ->assertForbidden();
});
