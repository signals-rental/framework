<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Verify your email"
        description="Please verify your email address by clicking on the link we just emailed to you."
    />

    @if (session('status') == 'verification-link-sent')
        <x-signals.alert type="success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-signals.alert>
    @endif

    <div class="flex flex-col gap-3">
        <button wire:click="sendVerification" type="button" class="s-btn s-btn-primary s-btn-block">{{ __('Resend verification email') }}</button>
        <button wire:click="logout" type="button" class="s-btn s-btn-ghost s-btn-block">{{ __('Log out') }}</button>
    </div>
</div>
