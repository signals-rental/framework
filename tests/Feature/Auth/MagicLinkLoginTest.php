<?php

use App\Mail\TemplatedEmail;
use App\Models\EmailTemplate;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkLoginNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Volt as LivewireVolt;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    settings()->set('security.magic_link_enabled', true, 'boolean');
});

// Shared `mintMagicLinkSecret()` lives in tests/Support/MagicLinkHelpers.php.

// ─── request side (login Volt component `sendMagicLink`) ──────────

it('queues the notification and shows the neutral confirmation for an eligible active user', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'staff@example.com']);

    LivewireVolt::test('auth.login')
        ->set('magicLinkEmail', 'staff@example.com')
        ->call('sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('magicLinkSent', true)
        ->assertSee("we've sent a sign-in link");

    Notification::assertSentTo($user, MagicLinkLoginNotification::class);
});

it('validates that a magic-link email is present and well formed', function () {
    Notification::fake();

    LivewireVolt::test('auth.login')
        ->set('magicLinkEmail', 'not-an-email')
        ->call('sendMagicLink')
        ->assertHasErrors('magicLinkEmail')
        ->assertSet('magicLinkSent', false);

    Notification::assertNothingSent();
});

it('shows the same neutral confirmation for an unknown email without sending', function () {
    Notification::fake();

    LivewireVolt::test('auth.login')
        ->set('magicLinkEmail', 'nobody@example.com')
        ->call('sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('magicLinkSent', true)
        ->assertSee("we've sent a sign-in link");

    Notification::assertNothingSent();
});

it('shows the neutral confirmation for an inactive user without sending', function () {
    Notification::fake();

    User::factory()->deactivated()->create(['email' => 'inactive@example.com']);

    LivewireVolt::test('auth.login')
        ->set('magicLinkEmail', 'inactive@example.com')
        ->call('sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('magicLinkSent', true)
        ->assertSee("we've sent a sign-in link");

    Notification::assertNothingSent();
});

it('shows the neutral confirmation but sends nothing when the feature is off', function () {
    Notification::fake();
    settings()->set('security.magic_link_enabled', false, 'boolean');

    User::factory()->create(['email' => 'staff@example.com']);

    LivewireVolt::test('auth.login')
        ->set('magicLinkEmail', 'staff@example.com')
        ->call('sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('magicLinkSent', true);

    Notification::assertNothingSent();
});

it('shows the neutral confirmation but sends nothing for an SSO-enforced user', function () {
    Notification::fake();
    Role::findOrCreate('Sales', 'web');
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create(['email' => 'sales@example.com']);
    $user->assignRole('Sales');

    LivewireVolt::test('auth.login')
        ->set('magicLinkEmail', 'sales@example.com')
        ->call('sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('magicLinkSent', true);

    Notification::assertNothingSent();
});

// ─── consume route (HTTP, `magic-link.login`) ────────────────────

it('logs in a user and redirects to the dashboard for a valid token', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $secret = mintMagicLinkSecret($user);

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('records an audit log entry for a successful magic-link login', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $secret = mintMagicLinkSecret($user);

    $this->get(route('magic-link.login', ['token' => $secret]));

    $this->assertDatabaseHas('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.magic_link_login',
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);
});

it('redirects an expired token to login with a generic error and does not authenticate', function () {
    $user = User::factory()->create();
    $secret = 'expired-secret-'.str_repeat('a', 50);
    MagicLinkToken::factory()->for($user)->expired()->create([
        'token_hash' => hash('sha256', $secret),
    ]);

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects an already-consumed token to login with a generic error', function () {
    $user = User::factory()->create();
    $secret = 'consumed-secret-'.str_repeat('b', 49);
    MagicLinkToken::factory()->for($user)->consumed()->create([
        'token_hash' => hash('sha256', $secret),
    ]);

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects an unknown token to login with a generic error', function () {
    $this->get(route('magic-link.login', ['token' => 'totally-unknown-token']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('denies a token when the feature is disabled after the link was minted', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $secret = mintMagicLinkSecret($user);

    settings()->set('security.magic_link_enabled', false, 'boolean');

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('denies a token when SSO is enforced after the link was minted', function () {
    Role::findOrCreate('Sales', 'web');
    $user = User::factory()->create(['email' => 'sales@example.com']);
    $secret = mintMagicLinkSecret($user);

    $user->assignRole('Sales');
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('clears stale passwordless audit flags when handing off to the 2FA challenge', function () {
    // A stale sso_provider flag from an abandoned SSO attempt must not survive a
    // later magic-link hand-off, or the 2FA challenge would mis-audit this login
    // as auth.sso_login. HandlesPasswordlessTwoFactor clears the whole registry
    // on every hand-off before setting the one flag for this login.
    Session::put('sso_provider', 'google');

    $user = User::factory()->withTwoFactor()->create(['email' => 'stale-flag@example.com']);
    $secret = mintMagicLinkSecret($user);

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('two-factor.challenge'));

    expect(Session::get('sso_provider'))->toBeNull();
    expect(Session::get('magic_link_login'))->toBeTrue();
});

it('hands a 2FA-enabled user to the challenge without authenticating', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'mfa@example.com']);
    $secret = mintMagicLinkSecret($user);

    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(Session::get('two_factor_user_id'))->toBe($user->id);
    expect(Session::get('magic_link_login'))->toBeTrue();
});

it('audits the magic-link login only after the 2FA challenge completes', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'mfa-audit@example.com']);
    $secret = mintMagicLinkSecret($user);

    // Step 1: consuming the link hands off to the challenge and stashes the flag.
    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('two-factor.challenge'));

    // No magic-link login is audited until the second factor completes the login.
    $this->assertDatabaseMissing('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.magic_link_login',
    ]);

    // Step 2: complete the challenge with a valid TOTP code.
    $code = app(Google2FA::class)->getCurrentOtp((string) $user->two_factor_secret);

    LivewireVolt::test('auth.two-factor-challenge')
        ->set('code', $code)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);

    $this->assertDatabaseHas('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.magic_link_login',
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);

    // The flag is cleared so a later password+2FA login isn't mis-audited.
    expect(Session::get('magic_link_login'))->toBeNull();
});

it('audits the magic-link login after the 2FA challenge completes via a recovery code', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'mfa-recovery@example.com']);
    $secret = mintMagicLinkSecret($user);

    // Step 1: consuming the link hands off to the challenge and stashes the flag.
    $this->get(route('magic-link.login', ['token' => $secret]))
        ->assertRedirect(route('two-factor.challenge'));

    // Step 2: complete the challenge with a valid recovery code (not TOTP).
    $codes = json_decode((string) $user->two_factor_recovery_codes, true);
    $validCode = $codes[0];

    LivewireVolt::test('auth.two-factor-challenge')
        ->set('useRecovery', true)
        ->set('recoveryCode', $validCode)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);

    $this->assertDatabaseHas('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.magic_link_login',
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);

    // Used recovery code is removed from the user's list.
    $remaining = json_decode((string) $user->fresh()->two_factor_recovery_codes, true);
    expect($remaining)->not->toContain($validCode);

    // Flag is cleared so a later password+2FA login is not mis-audited.
    expect(Session::get('magic_link_login'))->toBeNull();
});

// ─── login page show / hide (mirrors SsoLoginButtonsTest) ────────

it('renders the magic-link affordance when the feature is enabled', function () {
    settings()->set('security.magic_link_enabled', true, 'boolean');

    LivewireVolt::test('auth.login')
        ->assertSee('Email me a sign-in link');
});

it('does not render the magic-link affordance when the feature is disabled', function () {
    settings()->set('security.magic_link_enabled', false, 'boolean');

    LivewireVolt::test('auth.login')
        ->assertDontSee('Email me a sign-in link')
        ->assertDontSee('Send sign-in link');
});

// ─── notification template / fallback (mirrors PasswordResetTest) ─

it('renders the branded magic-link template with the sign-in url', function () {
    (new EmailTemplateSeeder)->run();

    $user = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    $notification = new MagicLinkLoginNotification('secret-'.str_repeat('a', 58));

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(TemplatedEmail::class);

    if (! $mail instanceof TemplatedEmail) {
        $this->fail('Expected a TemplatedEmail mailable.');
    }

    expect($mail->bodyHtml)->toContain('/auth/magic-link/');
});

it('falls back to a plain mail message when the magic-link template is inactive', function () {
    (new EmailTemplateSeeder)->run();
    EmailTemplate::where('key', 'magic_link')->update(['is_active' => false]);

    $secret = 'secret-'.str_repeat('b', 58);
    $user = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    $notification = new MagicLinkLoginNotification($secret);

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);

    if (! $mail instanceof MailMessage) {
        $this->fail('Expected a MailMessage.');
    }

    expect($mail->actionUrl)->toBe(route('magic-link.login', ['token' => $secret]));
});

it('falls back to a plain mail message when the magic-link template is missing', function () {
    // No EmailTemplateSeeder run: the magic_link template does not exist.
    $secret = 'secret-'.str_repeat('c', 58);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $notification = new MagicLinkLoginNotification($secret);

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);

    if (! $mail instanceof MailMessage) {
        $this->fail('Expected a MailMessage.');
    }

    expect($mail->actionUrl)->toBe(route('magic-link.login', ['token' => $secret]));
});
