<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public User $user;
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        if ($user->invitation_accepted_at) {
            redirect()->route('login');
        }
    }

    public function accept(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->user->update([
            'password' => Hash::make($this->password),
            'invitation_accepted_at' => now(),
        ]);

        Auth::login($this->user);

        redirect()->route('dashboard');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Accept invitation"
        :description="'Welcome, ' . $user->name . ' — set a password to finish creating your account.'"
    />

    <form wire:submit="accept" class="flex flex-col gap-5">
        <div class="s-field !mb-0">
            <label class="s-field-label">{{ __('Email address') }}</label>
            <input type="email" name="email" class="s-input" value="{{ $user->email }}" autocomplete="username" readonly>
        </div>

        <div class="s-field !mb-0 {{ $errors->has('password') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Password') }}</label>
            <input wire:model="password" type="password" name="password" class="s-input" required autocomplete="new-password" placeholder="Password">
            @error('password') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="s-field !mb-0 {{ $errors->has('password_confirmation') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Confirm password') }}</label>
            <input wire:model="password_confirmation" type="password" name="password_confirmation" class="s-input" required autocomplete="new-password" placeholder="Confirm password">
            @error('password_confirmation') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Set password & continue') }}</button>
    </form>
</div>
