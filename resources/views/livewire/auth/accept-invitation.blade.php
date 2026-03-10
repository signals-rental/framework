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

<section class="w-full max-w-md mx-auto mt-16">
    <div class="s-card">
        <div class="s-card-body space-y-6">
            <div class="text-center">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Welcome, {{ $user->name }}!</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                    Set your password to complete your account setup.
                </p>
            </div>

            <form wire:submit="accept" class="space-y-4">
                <flux:input wire:model="password" label="Password" type="password" required />
                <flux:input wire:model="password_confirmation" label="Confirm Password" type="password" required />

                <flux:button variant="primary" type="submit" class="w-full">Set Password & Continue</flux:button>
            </form>
        </div>
    </div>
</section>
