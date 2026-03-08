<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Confirm password"
        description="This is a secure area of the application. Please confirm your password before continuing."
    />

    @if (session('status'))
        <x-signals.alert type="success">{{ session('status') }}</x-signals.alert>
    @endif

    <form wire:submit="confirmPassword" class="flex flex-col gap-5">
        <div class="s-field !mb-0 {{ $errors->has('password') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Password') }}</label>
            <input wire:model="password" type="password" name="password" class="s-input" required autocomplete="current-password" placeholder="Password">
            @error('password') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Confirm') }}</button>
    </form>
</div>
