<?php

use App\Models\User;
use App\Services\Auth\SsoEnforcement;
use App\Services\Auth\SsoService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureSsoIsNotEnforced();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $lockoutSeconds = max(1, (int) settings('security.lockout_duration', 15)) * 60;
            RateLimiter::hit($this->throttleKey(), $lockoutSeconds);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if (Auth::user()->hasTwoFactorEnabled()) {
            $userId = Auth::id();
            Auth::logout();
            Session::forget('two_factor_confirmed');
            Session::regenerate();
            Session::put('two_factor_user_id', $userId);
            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Reject password login for users whose role requires Single Sign-On.
     *
     * When the entered email belongs to a user with an enforced role (and they
     * are not the Owner), the password is never checked — they must use the SSO
     * buttons instead. Unknown emails fall through so the generic failure path
     * does not leak which addresses exist.
     */
    protected function ensureSsoIsNotEnforced(): void
    {
        $user = User::query()->where('email', $this->email)->first();

        if ($user === null) {
            return;
        }

        if (! app(SsoEnforcement::class)->isEnforcedFor($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('Your organisation requires single sign-on. Please use the Google or Microsoft buttons above.'),
        ]);
    }

    /**
     * Ensure the authentication request is not rate limited.
     *
     * Uses SecuritySettings for max attempts and lockout duration.
     */
    protected function ensureIsNotRateLimited(): void
    {
        $maxAttempts = max(1, (int) settings('security.max_login_attempts', 5));

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    /**
     * Expose the enabled+configured SSO providers (in display order) to the view.
     *
     * @return array{ssoProviders: list<string>}
     */
    public function with(): array
    {
        return [
            'ssoProviders' => app(SsoService::class)->enabledProviders(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Log in" :description="config('signals.tenant') ? 'Signing in to ' . config('signals.tenant') : 'Welcome back.'" />

    @if (session('status'))
        <x-signals.alert type="success">{{ session('status') }}</x-signals.alert>
    @endif

    @if (! empty($ssoProviders))
        <div class="flex flex-col gap-3">
            @foreach ($ssoProviders as $provider)
                <a
                    href="{{ route('sso.redirect', ['provider' => $provider]) }}"
                    class="s-btn s-btn-block s-btn-lg"
                    aria-label="{{ __('Continue with :provider', ['provider' => ucfirst($provider)]) }}"
                >
                    @if ($provider === 'google')
                        <svg viewBox="0 0 18 18" width="18" height="18" aria-hidden="true" focusable="false">
                            <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                            <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
                            <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
                            <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
                        </svg>
                    @elseif ($provider === 'microsoft')
                        <svg viewBox="0 0 18 18" width="18" height="18" aria-hidden="true" focusable="false">
                            <path fill="#F25022" d="M0 0h8.583v8.583H0z"/>
                            <path fill="#7FBA00" d="M9.417 0H18v8.583H9.417z"/>
                            <path fill="#00A4EF" d="M0 9.417h8.583V18H0z"/>
                            <path fill="#FFB900" d="M9.417 9.417H18V18H9.417z"/>
                        </svg>
                    @endif
                    <span>{{ __('Continue with :provider', ['provider' => ucfirst($provider)]) }}</span>
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-3" role="separator" aria-hidden="true">
            <span class="h-px flex-1 bg-[var(--card-border)]"></span>
            <span class="s-auth-description uppercase tracking-wide">{{ __('or') }}</span>
            <span class="h-px flex-1 bg-[var(--card-border)]"></span>
        </div>
    @endif

    <form wire:submit="login" class="flex flex-col gap-5">
        <div class="s-field !mb-0 {{ $errors->has('email') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Email address') }}</label>
            <input wire:model="email" type="email" name="email" class="s-input" required autofocus autocomplete="username" placeholder="email@example.com">
            @error('email') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="s-field !mb-0 {{ $errors->has('password') ? 'has-error' : '' }}">
            <div class="s-auth-field-row">
                <label class="s-field-label">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="s-auth-description underline hover:opacity-80 transition-opacity">{{ __('Forgot your password?') }}</a>
                @endif
            </div>
            <input wire:model="password" type="password" name="password" class="s-input" required autocomplete="current-password" placeholder="Password">
            @error('password') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
            <x-signals.checkbox x-bind:class="{ 'checked': $wire.remember }" x-on:click="$wire.remember = !$wire.remember" />
            <span class="s-auth-description">{{ __('Remember me') }}</span>
        </label>

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Log in') }}</button>
    </form>
</div>
