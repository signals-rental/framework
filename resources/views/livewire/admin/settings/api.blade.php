<?php

use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('API')] class extends Component {
    public string $tokenName = '';

    /** @var list<string> */
    public array $selectedAbilities = [];

    public bool $showCreateModal = false;
    public bool $showTokenValue = false;
    public string $plainTextToken = '';
    public ?int $confirmingRevocation = null;

    /** @var array<string, string> */
    public array $availableAbilities = [
        'members:read' => 'Read members',
        'members:write' => 'Write members',
        'countries:read' => 'Read countries',
        'custom-fields:read' => 'Read custom fields',
        'custom-fields:write' => 'Write custom fields',
        'static-data:read' => 'Read static data (lists)',
        'static-data:write' => 'Write static data (lists)',
        'tax-classes:read' => 'Read tax classes',
        'tax-classes:write' => 'Write tax classes',
        'settings:read' => 'Read settings',
        'settings:write' => 'Write settings',
        'users:read' => 'Read users',
        'users:write' => 'Write users',
        'roles:read' => 'Read roles',
        'roles:write' => 'Write roles',
        'webhooks:manage' => 'Manage webhooks',
        'system:read' => 'Read system info',
        'action-log:read' => 'Read action logs',
    ];

    public function with(): array
    {
        return [
            'tokens' => auth()->user()->tokens()->orderByDesc('created_at')->get(),
        ];
    }

    public function openCreateModal(): void
    {
        $this->reset('tokenName', 'selectedAbilities', 'showTokenValue', 'plainTextToken');
        $this->showCreateModal = true;
    }

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
            'selectedAbilities' => ['required', 'array', 'min:1'],
            'selectedAbilities.*' => ['string', 'in:' . implode(',', array_keys($this->availableAbilities))],
        ]);

        /** @var NewAccessToken $token */
        $token = auth()->user()->createToken($this->tokenName, $this->selectedAbilities);

        $this->plainTextToken = $token->plainTextToken;
        $this->showCreateModal = false;
        $this->showTokenValue = true;

        $this->dispatch('token-created');
    }

    public function closeTokenDisplay(): void
    {
        $this->showTokenValue = false;
        $this->plainTextToken = '';
    }

    public function revokeToken(int $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();
        $this->confirmingRevocation = null;
        $this->dispatch('token-revoked');
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="users" title="API Tokens" description="Create and manage personal access tokens for API authentication.">
        <x-slot:actions>
            <flux:button variant="primary" wire:click="openCreateModal">Create Token</flux:button>
        </x-slot:actions>

        {{-- Token Created Banner --}}
        @if($showTokenValue)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Token created successfully</p>
                        <p class="mt-1 text-xs text-green-700 dark:text-green-300">Copy this token now. It will not be shown again.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code class="block flex-1 rounded bg-green-100 px-3 py-2 font-mono text-sm text-green-900 dark:bg-green-900 dark:text-green-100 break-all">{{ $plainTextToken }}</code>
                            <button
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js($plainTextToken)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="s-btn s-btn-sm s-btn-ghost shrink-0"
                                title="Copy to clipboard"
                            >
                                <template x-if="!copied"><flux:icon.clipboard class="w-4 h-4" /></template>
                                <template x-if="copied"><flux:icon.check class="w-4 h-4 text-green-600" /></template>
                            </button>
                        </div>
                        <div class="mt-2">
                            <flux:button variant="ghost" size="sm" wire:click="closeTokenDisplay">Dismiss</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Tokens Table --}}
        @if($tokens->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Abilities</th>
                            <th>Last Used</th>
                            <th>Created</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tokens as $token)
                            <tr wire:key="token-{{ $token->id }}">
                                <td class="font-medium">{{ $token->name }}</td>
                                <td>
                                    @foreach($token->abilities as $ability)
                                        <span class="s-badge">{{ $ability }}</span>
                                    @endforeach
                                </td>
                                <td class="text-sm text-zinc-500">
                                    {{ $token->last_used_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="text-sm text-zinc-500">
                                    {{ $token->created_at->diffForHumans() }}
                                </td>
                                <td>
                                    <button
                                        class="s-btn s-btn-ghost s-btn-sm text-red-600 hover:text-red-700"
                                        wire:click="$set('confirmingRevocation', {{ $token->id }})"
                                        title="Revoke token"
                                    >
                                        <flux:icon.trash class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:icon.key class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No API tokens</p>
                <p class="mt-1 text-sm text-zinc-500">Create a token to authenticate with the API.</p>
            </div>
        @endif

        <x-action-message on="token-created">Token created successfully.</x-action-message>
        <x-action-message on="token-revoked">Token revoked.</x-action-message>

        {{-- Create Token Modal --}}
        <flux:modal wire:model="showCreateModal">
            <div class="space-y-6">
                <flux:heading size="lg">Create API Token</flux:heading>

                <form wire:submit="createToken" class="space-y-4">
                    <flux:input wire:model="tokenName" label="Token Name" placeholder="e.g. CI/CD Pipeline" required />

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Abilities</label>
                        <div class="space-y-2">
                            @foreach($availableAbilities as $ability => $label)
                                <label class="flex items-center gap-2 cursor-pointer" wire:key="ability-{{ $loop->index }}"
                                       x-data="{ checked: @js(in_array($ability, $selectedAbilities)) }"
                                       x-init="$watch('$wire.selectedAbilities', v => checked = v.includes('{{ $ability }}'))">
                                    <input type="checkbox" wire:model="selectedAbilities" value="{{ $ability }}" class="hidden" x-on:change="checked = $el.checked" />
                                    <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                    <span class="text-sm">{{ $label }}</span>
                                    <span class="text-xs text-zinc-400 font-mono">{{ $ability }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedAbilities') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                        <flux:button variant="primary" type="submit">Create Token</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Revocation Confirmation --}}
        @if($confirmingRevocation)
            <flux:modal wire:model.self="confirmingRevocation">
                <div class="space-y-4">
                    <flux:heading size="lg">Revoke Token</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to revoke this token? Any applications using this token will no longer be able to access the API.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingRevocation', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="revokeToken({{ $confirmingRevocation }})">Revoke Token</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
