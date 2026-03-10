<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Forgot password" description="Enter your email to receive a password reset link" />

    @if (session('status'))
        <x-signals.alert type="success">{{ session('status') }}</x-signals.alert>
    @endif

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-5">
        <div class="s-field !mb-0 {{ $errors->has('email') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Email address') }}</label>
            <input wire:model="email" type="email" name="email" class="s-input" required autofocus placeholder="email@example.com">
            @error('email') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Email password reset link') }}</button>
    </form>

    <p class="s-auth-description">
        Or, <a href="{{ route('login') }}" wire:navigate class="underline hover:opacity-80 transition-opacity">return to log in</a>
    </p>
</div>
