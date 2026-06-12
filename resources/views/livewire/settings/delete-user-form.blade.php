<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Whether the authenticated user is allowed to delete their own account.
     */
    public function canDeleteSelf(): bool
    {
        return $this->selfDeleteBlockReason() === null;
    }

    /**
     * The reason the user cannot self-delete, or null if they can.
     */
    public function selfDeleteBlockReason(): ?string
    {
        $user = Auth::user();

        if ($user->isOwner()) {
            return __('You are the account owner. Transfer ownership before deleting your account.');
        }

        if (User::where('is_active', true)->count() <= 1) {
            return __('You are the last user in the system and cannot delete your account.');
        }

        return null;
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        if ($reason = $this->selfDeleteBlockReason()) {
            $this->addError('password', $reason);

            return;
        }

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

        @php($selfDeleteBlockReason = $this->selfDeleteBlockReason())

        @if($selfDeleteBlockReason)
            <p class="mt-4 text-sm text-[var(--text-danger)]">
                {{ $selfDeleteBlockReason }}
            </p>
        @else
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
        @endif
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
