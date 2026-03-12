<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Profile')] class extends Component {
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    <x-settings.layout heading="Profile" subheading="Update your name and email address">
        <x-signals.form-section title="Profile information">
            <form wire:submit="updateProfileInformation" class="space-y-6">
                <flux:input wire:model="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" label="{{ __('Email') }}" type="email" name="email" required autocomplete="email" />

                    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                        <div class="mt-2 space-y-1">
                            <p class="text-sm text-[var(--text-secondary)]">
                                {{ __('Your email address is unverified.') }}

                                <button
                                    wire:click.prevent="resendVerificationNotification"
                                    class="underline hover:text-[var(--text-primary)]"
                                >
                                    {{ __('Click here to re-send the verification email.') }}
                                </button>
                            </p>

                            @if (session('status') === 'verification-link-sent')
                                <p class="text-sm font-medium text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                    <x-action-message on="profile-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </x-signals.form-section>

        <div class="mt-8">
            <livewire:settings.two-factor-authentication-form />
        </div>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
