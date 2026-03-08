<?php

use App\Actions\Auth\ConfirmTwoFactor;
use App\Actions\Auth\DisableTwoFactor;
use App\Actions\Auth\EnableTwoFactor;
use App\Actions\Auth\RegenerateTwoFactorRecoveryCodes;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    /** Current step: idle | confirming_enable | showing_qr | showing_recovery | confirming_disable */
    public string $step = 'idle';

    public string $password = '';
    public string $code = '';
    public string $qrSvg = '';
    public string $secret = '';

    /** @var array<int, string> */
    public array $recoveryCodes = [];

    public function mount(): void
    {
        $this->step = 'idle';
    }

    /**
     * Begin the enable flow — ask for password confirmation.
     */
    public function startEnable(): void
    {
        $this->step = 'confirming_enable';
        $this->password = '';
    }

    /**
     * Verify password, generate secret, and show QR code.
     */
    public function enableTwoFactor(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The password you entered is incorrect.')],
            ]);
        }

        $otpUrl = app(EnableTwoFactor::class)($user);

        $this->secret = (string) $user->two_factor_secret;
        $this->qrSvg = $this->renderQrSvg($otpUrl);
        $this->password = '';
        $this->code = '';
        $this->step = 'showing_qr';
    }

    /**
     * Confirm the TOTP code and activate 2FA.
     */
    public function confirmTwoFactor(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        app(ConfirmTwoFactor::class)($user, $this->code);

        $this->recoveryCodes = json_decode((string) $user->two_factor_recovery_codes, true) ?? [];
        $this->code = '';
        $this->step = 'showing_recovery';
    }

    /**
     * Show existing recovery codes (for already-enabled 2FA).
     */
    public function showRecoveryCodes(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->recoveryCodes = json_decode((string) $user->two_factor_recovery_codes, true) ?? [];
        $this->step = 'showing_recovery';
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->recoveryCodes = app(RegenerateTwoFactorRecoveryCodes::class)($user);
    }

    /**
     * Begin the disable flow.
     */
    public function startDisable(): void
    {
        $this->password = '';
        $this->step = 'confirming_disable';
    }

    /**
     * Verify password and disable 2FA.
     */
    public function disableTwoFactor(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The password you entered is incorrect.')],
            ]);
        }

        app(DisableTwoFactor::class)($user);

        $this->password = '';
        $this->step = 'idle';
    }

    /**
     * Cancel any in-progress flow and return to idle.
     */
    public function cancel(): void
    {
        $this->step = 'idle';
        $this->password = '';
        $this->code = '';
        $this->recoveryCodes = [];
    }

    /**
     * Render the OTP URL as an inline SVG string.
     */
    private function renderQrSvg(string $otpUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($otpUrl);
    }
}; ?>

<x-signals.form-section title="Two-Factor Authentication">
    <x-slot:headerActions>
        @if (Auth::user()->hasTwoFactorEnabled())
            <span class="s-badge s-badge--success">{{ __('Enabled') }}</span>
        @else
            <span class="s-badge s-badge--neutral">{{ __('Disabled') }}</span>
        @endif
    </x-slot:headerActions>

    {{-- Idle state --}}
    @if ($step === 'idle')
        <p class="text-sm text-[var(--text-secondary)] mb-4">
            {{ __('Add an extra layer of security to your account by requiring an authentication code from your phone in addition to your password.') }}
        </p>

        @if (Auth::user()->hasTwoFactorEnabled())
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="showRecoveryCodes" size="sm">
                    {{ __('View Recovery Codes') }}
                </flux:button>
                <flux:button wire:click="startDisable" variant="danger" size="sm">
                    {{ __('Disable') }}
                </flux:button>
            </div>
        @else
            <flux:button wire:click="startEnable" size="sm">
                {{ __('Enable') }}
            </flux:button>
        @endif
    @endif

    {{-- Password confirmation before enabling --}}
    @if ($step === 'confirming_enable')
        <form wire:submit="enableTwoFactor" class="space-y-4">
            <p class="text-sm text-[var(--text-secondary)]">
                {{ __('Confirm your password to begin setting up two-factor authentication.') }}
            </p>
            <flux:input
                wire:model="password"
                label="{{ __('Current Password') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" size="sm">{{ __('Continue') }}</flux:button>
                <flux:button wire:click="cancel" type="button" size="sm">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- QR code + confirmation --}}
    @if ($step === 'showing_qr')
        <div class="space-y-4">
            <p class="text-sm text-[var(--text-secondary)]">
                {{ __('Scan the QR code below with your authenticator app (e.g. Google Authenticator, Authy), then enter the 6-digit code to confirm setup.') }}
            </p>

            <div class="inline-block rounded-lg border border-[var(--border)] bg-white p-3">
                {!! $qrSvg !!}
            </div>

            <div>
                <p class="text-xs font-medium text-[var(--text-secondary)] mb-1">{{ __('Setup key (manual entry)') }}</p>
                <code class="text-xs font-mono bg-[var(--bg-subtle)] px-2 py-1 rounded">{{ $secret }}</code>
            </div>

            <form
                wire:submit="confirmTwoFactor"
                class="space-y-3"
                x-data
            >
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-[var(--text-primary)]">{{ __('Verification code') }}</label>
                    <x-signals.otp-input
                        length="6"
                        x-on:otp-complete="$wire.set('code', $event.detail.code)"
                    />
                    @error('code')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" size="sm">{{ __('Confirm') }}</flux:button>
                    <flux:button wire:click="cancel" type="button" size="sm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- Recovery codes display --}}
    @if ($step === 'showing_recovery')
        <div class="space-y-4">
            <x-signals.alert>
                {{ __('Save these recovery codes in a secure location. Each code can only be used once. If you lose access to your authenticator app, these codes are the only way to regain access to your account.') }}
            </x-signals.alert>

            <div class="grid grid-cols-2 gap-2 font-mono text-sm bg-[var(--bg-subtle)] rounded-lg p-4">
                @foreach ($recoveryCodes as $recoveryCode)
                    <span>{{ $recoveryCode }}</span>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="regenerateRecoveryCodes" size="sm">
                    {{ __('Regenerate Codes') }}
                </flux:button>
                <flux:button wire:click="cancel" size="sm" variant="filled">
                    {{ __('Done') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Disable confirmation --}}
    @if ($step === 'confirming_disable')
        <form wire:submit="disableTwoFactor" class="space-y-4">
            <x-signals.alert type="warning">
                {{ __('Disabling two-factor authentication will reduce the security of your account.') }}
            </x-signals.alert>
            <flux:input
                wire:model="password"
                label="{{ __('Confirm your password to disable 2FA') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <div class="flex gap-2">
                <flux:button type="submit" variant="danger" size="sm">{{ __('Disable 2FA') }}</flux:button>
                <flux:button wire:click="cancel" type="button" size="sm">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif
</x-signals.form-section>
