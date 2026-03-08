<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Reset password" description="Please enter your new password below" />

    @if (session('status'))
        <x-signals.alert type="success">{{ session('status') }}</x-signals.alert>
    @endif

    <form wire:submit="resetPassword" class="flex flex-col gap-5">
        <div class="s-field !mb-0 {{ $errors->has('email') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Email') }}</label>
            <input wire:model="email" type="email" name="email" class="s-input" required autocomplete="email">
            @error('email') <div class="s-field-error">{{ $message }}</div> @enderror
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

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Reset password') }}</button>
    </form>
</div>
