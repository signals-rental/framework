<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use PragmaRX\Google2FA\Google2FA;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $code = '';
    public string $recoveryCode = '';
    public bool $useRecovery = false;

    /**
     * Redirect to login if no pending 2FA session exists.
     */
    public function mount(): void
    {
        if (! Session::get('two_factor_user_id')) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    /**
     * Toggle between TOTP code and recovery code mode.
     */
    public function toggleRecovery(): void
    {
        $this->useRecovery = ! $this->useRecovery;
        $this->code = '';
        $this->recoveryCode = '';
    }

    /**
     * Verify the two-factor challenge and log the user in.
     */
    public function authenticate(): void
    {
        $userId = Session::get('two_factor_user_id');

        if (! $userId) {
            abort(403);
        }

        $throttleKey = 'two-factor.'.$userId.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                $this->useRecovery ? 'recoveryCode' : 'code' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $user = User::find($userId);

        if (! $user) {
            abort(403);
        }

        if ($this->useRecovery) {
            $this->verifyRecoveryCode($user, $throttleKey);
        } else {
            $this->verifyTotpCode($user, $throttleKey);
        }

        Auth::loginUsingId($userId);
        Session::forget('two_factor_user_id');
        Session::put('two_factor_confirmed', true);
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Verify a TOTP code against the user's secret.
     */
    private function verifyTotpCode(User $user, string $throttleKey): void
    {
        $google2fa = app(Google2FA::class);

        if (! $google2fa->verifyKey((string) $user->two_factor_secret, $this->code)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'code' => [__('The provided two-factor authentication code was invalid.')],
            ]);
        }

        RateLimiter::clear($throttleKey);
    }

    /**
     * Verify a recovery code, removing it from the user's list on success.
     */
    private function verifyRecoveryCode(User $user, string $throttleKey): void
    {
        $codes = json_decode((string) $user->two_factor_recovery_codes, true) ?? [];
        $index = null;

        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $this->recoveryCode)) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'recoveryCode' => [__('The provided two-factor recovery code was invalid.')],
            ]);
        }

        array_splice($codes, (int) $index, 1);

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode($codes),
        ])->save();

        RateLimiter::clear($throttleKey);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Two-factor authentication"
        description="{{ $useRecovery ? __('Enter one of your recovery codes to continue.') : __('Enter the authentication code from your app to continue.') }}"
    />

    <form wire:submit="authenticate" class="flex flex-col gap-5">
        @if (! $useRecovery)
            <div class="s-field !mb-0 {{ $errors->has('code') ? 'has-error' : '' }}">
                <label class="s-field-label">{{ __('Authentication code') }}</label>
                <x-signals.otp-input
                    length="6"
                    x-on:otp-complete="$wire.set('code', $event.detail.code)"
                />
                @error('code') <div class="s-field-error">{{ $message }}</div> @enderror
            </div>
        @else
            <div class="s-field !mb-0 {{ $errors->has('recoveryCode') ? 'has-error' : '' }}">
                <label class="s-field-label">{{ __('Recovery code') }}</label>
                <input wire:model="recoveryCode" type="text" name="recovery_code" class="s-input" required autocomplete="one-time-code" placeholder="XXXX-XXXX">
                @error('recoveryCode') <div class="s-field-error">{{ $message }}</div> @enderror
            </div>
        @endif

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Verify') }}</button>
    </form>

    <div class="text-center">
        <button wire:click="toggleRecovery" type="button" class="s-btn s-btn-ghost s-btn-sm">
            {{ $useRecovery ? __('Use an authentication app instead') : __('Use a recovery code instead') }}
        </button>
    </div>
</div>
