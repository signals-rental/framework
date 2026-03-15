<?php

use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\DeleteUser;
use App\Actions\Admin\ReactivateUser;
use App\Actions\Admin\SendPasswordReset;
use App\Actions\Admin\UpdateUser;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] #[Title('User')] class extends Component {
    public int $userId;
    public string $userName = '';
    public string $userEmail = '';
    /** @var list<string> */
    public array $selectedRoles = [];

    public bool $isOwner = false;
    public bool $isDeactivated = false;
    public bool $isInvited = false;
    public bool $hasPassword = false;

    public ?int $confirmingDeactivation = null;
    public ?int $confirmingDeletion = null;

    public function mount(User $user): void
    {
        Gate::authorize('users.edit');

        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->selectedRoles = $user->roles->pluck('name')->all();
        $this->isOwner = $user->isOwner();
        $this->isDeactivated = ! $user->isActive();
        $this->isInvited = $user->invited_at && ! $user->invitation_accepted_at;
        $this->hasPassword = (bool) $user->password;
    }

    public function with(): array
    {
        return [
            'availableRoles' => Role::query()
                ->orderBy('sort_order')
                ->get(),
            'isEditing' => true,
        ];
    }

    public function save(): void
    {
        $this->validate([
            'userName' => ['required', 'string', 'max:255'],
            'userEmail' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->userId],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($this->userId);

        (new UpdateUser)($user, [
            'name' => $this->userName,
            'email' => $this->userEmail,
            'roles' => $this->selectedRoles,
        ]);

        $this->redirect(route('admin.settings.users'), navigate: true);
    }

    public function deactivate(): void
    {
        $user = User::findOrFail($this->userId);
        (new DeactivateUser)($user);
        $this->confirmingDeactivation = null;
        $this->isDeactivated = true;
        $this->dispatch('user-deactivated');
    }

    public function reactivate(): void
    {
        $user = User::findOrFail($this->userId);
        (new ReactivateUser)($user);
        $this->isDeactivated = false;
        $this->dispatch('user-reactivated');
    }

    public function sendPasswordReset(): void
    {
        $user = User::findOrFail($this->userId);
        (new SendPasswordReset)($user);
        $this->dispatch('password-reset-sent');
    }

    public function resendInvitation(): void
    {
        Gate::authorize('users.invite');

        $user = User::findOrFail($this->userId);

        if (! $user->invited_at || $user->invitation_accepted_at) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user' => 'This user does not have a pending invitation.',
            ]);
        }

        $user->notify(new UserInvitedNotification);
        $this->dispatch('invitation-resent');
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->userId);
        (new DeleteUser)($user);
        $this->redirect(route('admin.settings.users'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="users" title="Edit User" description="Update user details, roles, and manage account actions.">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Users', 'href' => route('admin.settings.users')],
                ['label' => 'Edit User'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.users') }}" wire:navigate>
                Back to Users
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            {{-- User Details --}}
            <x-signals.form-section title="User Details">
                <div class="space-y-4">
                    <flux:input wire:model="userName" label="Name" required />
                    <flux:input wire:model="userEmail" label="Email" type="email" required />
                </div>
            </x-signals.form-section>

            {{-- Roles --}}
            <x-signals.form-section title="Roles">
                @if($isOwner)
                    <p class="text-sm text-zinc-500 mb-3">Owners have implicit access to everything. Additional roles are optional.</p>
                @endif
                <div class="space-y-2">
                    @foreach($availableRoles as $role)
                        <label class="flex items-center gap-2 cursor-pointer" wire:key="role-{{ $role->id }}"
                               x-data="{ checked: @js(in_array($role->name, $selectedRoles)) }"
                               x-init="$watch('$wire.selectedRoles', v => checked = v.includes('{{ $role->name }}'))">
                            <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">{{ $role->name }}</span>
                            @if($role->description)
                                <span class="text-xs text-zinc-500">- {{ $role->description }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('selectedRoles') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.users') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>

        {{-- Account Actions --}}
        @unless($isOwner)
            <x-signals.form-section title="Account Actions">
                <div class="flex flex-wrap gap-3">
                    @if($isInvited)
                        <flux:button variant="ghost" wire:click="resendInvitation">Resend Invitation</flux:button>
                    @endif

                    @if(! $isDeactivated && $hasPassword)
                        <flux:button variant="ghost" wire:click="sendPasswordReset">Send Password Reset</flux:button>
                    @endif

                    @if($isDeactivated)
                        <flux:button variant="ghost" wire:click="reactivate">Reactivate User</flux:button>
                    @else
                        <flux:button variant="danger" wire:click="$set('confirmingDeactivation', {{ $userId }})">Deactivate User</flux:button>
                    @endif

                    @can('users.delete')
                        <flux:button variant="danger" wire:click="$set('confirmingDeletion', {{ $userId }})">Delete User</flux:button>
                    @endcan
                </div>
            </x-signals.form-section>
        @endunless

        <x-action-message on="user-deactivated">User deactivated.</x-action-message>
        <x-action-message on="user-reactivated">User reactivated.</x-action-message>
        <x-action-message on="password-reset-sent">Password reset email sent.</x-action-message>
        <x-action-message on="invitation-resent">Invitation resent.</x-action-message>

        {{-- Deactivation Confirmation --}}
        @if($confirmingDeactivation)
            <flux:modal wire:model.self="confirmingDeactivation">
                <div class="space-y-4">
                    <flux:heading size="lg">Deactivate User</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to deactivate this user? They will no longer be able to log in and all their API tokens will be revoked.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeactivation', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deactivate">Deactivate</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Deletion Confirmation --}}
        @if($confirmingDeletion)
            <flux:modal wire:model.self="confirmingDeletion">
                <div class="space-y-4">
                    <flux:heading size="lg">Delete User</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to permanently delete this user? This action cannot be undone. All API tokens will be revoked and the user record will be removed.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeletion', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deleteUser">Delete Permanently</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
