<?php

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
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Log in to your account" :description="config('signals.tenant') ? 'Signing in to ' . config('signals.tenant') : 'Enter your email and password below to log in'" />

    @if (session('status'))
        <x-signals.alert type="success">{{ session('status') }}</x-signals.alert>
    @endif

    <form wire:submit="login" class="flex flex-col gap-5">
        <div class="s-field !mb-0 {{ $errors->has('email') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Email address') }}</label>
            <input wire:model="email" type="email" name="email" class="s-input" required autofocus autocomplete="email" placeholder="email@example.com">
            @error('email') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="s-field !mb-0 {{ $errors->has('password') ? 'has-error' : '' }}">
            <div class="flex items-center justify-between">
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
