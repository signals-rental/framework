<?php

use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\InviteUser;
use App\Actions\Admin\ReactivateUser;
use App\Actions\Admin\SendPasswordReset;
use App\Actions\Admin\TransferOwnership;
use App\Data\Admin\InviteUserData;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] class extends Component {
    // Invite modal
    public bool $showInviteModal = false;
    public string $inviteName = '';
    public string $inviteEmail = '';
    /** @var list<string> */
    public array $inviteRoles = [];

    // Confirmation
    public ?int $confirmingDeactivation = null;
    public ?int $confirmingTransfer = null;

    public function with(): array
    {
        return [
            'users' => User::query()
                ->with('roles')
                ->orderByDesc('is_owner')
                ->orderByDesc('is_admin')
                ->orderBy('name')
                ->get(),
            'availableRoles' => Role::query()
                ->where('name', '!=', 'Owner')
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function openInviteModal(): void
    {
        if (Role::query()->count() === 0) {
            $this->addError('invite', 'Please create at least one role before inviting users.');

            return;
        }

        $this->reset('inviteName', 'inviteEmail', 'inviteRoles');
        $this->showInviteModal = true;
    }

    public function invite(): void
    {
        $this->validate([
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'inviteRoles' => ['required', 'array', 'min:1'],
            'inviteRoles.*' => ['string', 'exists:roles,name'],
        ]);

        $data = InviteUserData::from([
            'name' => $this->inviteName,
            'email' => $this->inviteEmail,
            'roles' => $this->inviteRoles,
        ]);

        (new InviteUser)($data);

        $this->showInviteModal = false;
        $this->dispatch('user-invited');
    }

    public function deactivate(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new DeactivateUser)($user);
        $this->confirmingDeactivation = null;
        $this->dispatch('user-deactivated');
    }

    public function reactivate(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new ReactivateUser)($user);
        $this->dispatch('user-reactivated');
    }

    public function sendPasswordReset(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new SendPasswordReset)($user);
        $this->dispatch('password-reset-sent');
    }

    public function resendInvitation(int $userId): void
    {
        Gate::authorize('users.invite');

        $user = User::findOrFail($userId);

        if (! $user->invited_at || $user->invitation_accepted_at) {
            $this->addError('user', 'This user was not invited or has already accepted.');

            return;
        }

        $user->notify(new UserInvitedNotification);
        $this->dispatch('invitation-resent');
    }

    public function transferOwnership(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new TransferOwnership)($user);
        $this->confirmingTransfer = null;
        $this->dispatch('ownership-transferred');
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Users" description="Manage users, invitations, and access.">
        <x-slot:actions>
            @if($availableRoles->isNotEmpty())
                <flux:button variant="primary" wire:click="openInviteModal">Invite User</flux:button>
            @else
                <flux:button variant="primary" disabled tooltip="Create at least one role before inviting users">Invite User</flux:button>
            @endif
        </x-slot:actions>

        {{-- Users Table --}}
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="font-medium">
                                <a href="{{ route('admin.settings.users.edit', $user) }}" wire:navigate class="hover:underline">
                                    {{ $user->name }}
                                </a>
                                @if($user->is_owner)
                                    <span class="s-badge s-badge-amber ml-1">Owner</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach($user->roles as $role)
                                    <span class="s-badge">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if(! $user->isActive())
                                    <span class="s-status s-status-red">Deactivated</span>
                                @elseif($user->invited_at && ! $user->invitation_accepted_at)
                                    <span class="s-status s-status-amber">Invited</span>
                                @else
                                    <span class="s-status s-status-green">Active</span>
                                @endif
                            </td>
                            <td class="text-sm text-zinc-500">
                                {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('admin.settings.users.edit', $user) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                    @if(! $user->is_owner)
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" class="s-btn s-btn-ghost s-btn-sm">
                                                <flux:icon.ellipsis-vertical class="w-4 h-4" />
                                            </button>
                                            <div x-show="open" @click.away="open = false" x-cloak
                                                 class="s-dropdown" style="right: 0; top: 100%;">
                                                <a class="s-dropdown-item" href="{{ route('admin.settings.users.edit', $user) }}" wire:navigate>
                                                    Edit
                                                </a>
                                                @if($user->invited_at && ! $user->invitation_accepted_at)
                                                    <button class="s-dropdown-item" wire:click="resendInvitation({{ $user->id }})" @click="open = false">
                                                        Resend Invitation
                                                    </button>
                                                @endif
                                                @if($user->isActive() && $user->password)
                                                    <button class="s-dropdown-item" wire:click="sendPasswordReset({{ $user->id }})" @click="open = false">
                                                        Send Password Reset
                                                    </button>
                                                @endif
                                                <hr class="s-dropdown-sep" />
                                                @if($user->isActive())
                                                    <button class="s-dropdown-item text-red-600" wire:click="$set('confirmingDeactivation', {{ $user->id }})" @click="open = false">
                                                        Deactivate
                                                    </button>
                                                @else
                                                    <button class="s-dropdown-item" wire:click="reactivate({{ $user->id }})" @click="open = false">
                                                        Reactivate
                                                    </button>
                                                @endif
                                                @if(auth()->user()->is_owner)
                                                    <button class="s-dropdown-item" wire:click="$set('confirmingTransfer', {{ $user->id }})" @click="open = false">
                                                        Transfer Ownership
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-action-message on="user-invited">User invited successfully.</x-action-message>
        <x-action-message on="user-deactivated">User deactivated.</x-action-message>
        <x-action-message on="user-reactivated">User reactivated.</x-action-message>
        <x-action-message on="password-reset-sent">Password reset email sent.</x-action-message>
        <x-action-message on="invitation-resent">Invitation resent.</x-action-message>
        <x-action-message on="ownership-transferred">Ownership transferred.</x-action-message>

        {{-- Invite Modal --}}
        <flux:modal wire:model="showInviteModal">
            <div class="space-y-6">
                <flux:heading size="lg">Invite User</flux:heading>

                <form wire:submit="invite" class="space-y-4">
                    <flux:input wire:model="inviteName" label="Name" required />
                    <flux:input wire:model="inviteEmail" label="Email" type="email" required />

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Roles</label>
                        <div class="space-y-2">
                            @foreach($availableRoles as $role)
                                <label class="flex items-center gap-2 cursor-pointer" wire:key="invite-role-{{ $role->id }}"
                                       x-data="{ checked: @js(in_array($role->name, $inviteRoles)) }"
                                       x-init="$watch('$wire.inviteRoles', v => checked = v.includes('{{ $role->name }}'))">
                                    <input type="checkbox" wire:model="inviteRoles" value="{{ $role->name }}" class="hidden" x-on:change="checked = $el.checked" />
                                    <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                    <span class="text-sm">{{ $role->name }}</span>
                                    @if($role->description)
                                        <span class="text-xs text-zinc-500">- {{ $role->description }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('inviteRoles') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('showInviteModal', false)">Cancel</flux:button>
                        <flux:button variant="primary" type="submit">Send Invitation</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

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
                        <flux:button variant="danger" wire:click="deactivate({{ $confirmingDeactivation }})">Deactivate</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Transfer Ownership Confirmation --}}
        @if($confirmingTransfer)
            <flux:modal wire:model.self="confirmingTransfer">
                <div class="space-y-4">
                    <flux:heading size="lg">Transfer Ownership</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to transfer account ownership? You will lose owner privileges and this action can only be undone by the new owner.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingTransfer', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="transferOwnership({{ $confirmingTransfer }})">Transfer Ownership</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
