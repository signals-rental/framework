<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-6 space-y-6">
    <x-signals.form-section title="Delete Account">
        <p class="text-sm text-[var(--text-secondary)]">
            {{ __('Permanently delete your account and all of its resources.') }}
        </p>

        <div class="mt-4">
            <flux:button
                variant="danger"
                size="sm"
                x-data
                x-on:click="$dispatch('open-modal', 'confirm-user-deletion')"
            >
                {{ __('Delete Account') }}
            </flux:button>
        </div>
    </x-signals.form-section>

    <x-signals.modal name="confirm-user-deletion" title="{{ __('Delete Account') }}">
        <form wire:submit="deleteUser" class="space-y-4">
            <p class="text-sm text-[var(--text-secondary)]">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm.') }}
            </p>

            <flux:input wire:model="password" label="{{ __('Password') }}" type="password" name="password" />

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        variant="filled"
                        x-data
                        x-on:click="$dispatch('close-modal', 'confirm-user-deletion')"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="danger" type="submit">{{ __('Delete Account') }}</flux:button>
                </div>
            </x-slot:footer>
        </form>
    </x-signals.modal>
</section>
