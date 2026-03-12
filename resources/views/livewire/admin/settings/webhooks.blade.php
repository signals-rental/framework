<?php

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Webhooks')] class extends Component {
    // Modal states
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public bool $showLogsModal = false;

    // Create form
    public string $createUrl = '';
    /** @var list<string> */
    public array $createEvents = [];

    // Edit form
    public ?int $editingWebhookId = null;
    public string $editUrl = '';
    /** @var list<string> */
    public array $editEvents = [];
    public bool $editIsActive = true;

    // Delete
    public ?int $deletingWebhookId = null;

    // Logs
    public ?int $viewingLogsWebhookId = null;

    // Secret display (after creation)
    public bool $showSecret = false;
    public string $plainSecret = '';

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $logs = [];
        if ($this->viewingLogsWebhookId) {
            $logs = WebhookLog::query()
                ->where('webhook_id', $this->viewingLogsWebhookId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return [
            'webhooks' => Webhook::query()
                ->with('user')
                ->latest()
                ->get(),
            'availableEvents' => WebhookService::EVENTS,
            'logs' => $logs,
        ];
    }

    public function openCreateModal(): void
    {
        $this->reset('createUrl', 'createEvents');
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function createWebhook(): void
    {
        $this->validate([
            'createUrl' => ['required', 'url', 'max:2048'],
            'createEvents' => ['required', 'array', 'min:1'],
            'createEvents.*' => ['string', 'in:' . implode(',', WebhookService::EVENTS)],
        ]);

        $secret = Str::random(32);

        Webhook::create([
            'user_id' => auth()->id(),
            'url' => $this->createUrl,
            'secret' => $secret,
            'events' => $this->createEvents,
            'is_active' => true,
            'consecutive_failures' => 0,
        ]);

        $this->showCreateModal = false;
        $this->plainSecret = $secret;
        $this->showSecret = true;

        $this->reset('createUrl', 'createEvents');
        $this->dispatch('webhook-created');
    }

    public function dismissSecret(): void
    {
        $this->showSecret = false;
        $this->plainSecret = '';
    }

    public function openEditModal(int $webhookId): void
    {
        $webhook = Webhook::findOrFail($webhookId);

        $this->editingWebhookId = $webhook->id;
        $this->editUrl = $webhook->url;
        $this->editEvents = $webhook->events;
        $this->editIsActive = $webhook->is_active;

        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function updateWebhook(): void
    {
        $this->validate([
            'editUrl' => ['required', 'url', 'max:2048'],
            'editEvents' => ['required', 'array', 'min:1'],
            'editEvents.*' => ['string', 'in:' . implode(',', WebhookService::EVENTS)],
        ]);

        $webhook = Webhook::findOrFail($this->editingWebhookId);

        $webhook->update([
            'url' => $this->editUrl,
            'events' => $this->editEvents,
            'is_active' => $this->editIsActive,
        ]);

        $this->showEditModal = false;
        $this->editingWebhookId = null;
        $this->dispatch('webhook-updated');
    }

    public function confirmDelete(int $webhookId): void
    {
        $this->deletingWebhookId = $webhookId;
        $this->showDeleteModal = true;
    }

    public function deleteWebhook(): void
    {
        if ($this->deletingWebhookId) {
            Webhook::where('id', $this->deletingWebhookId)->delete();
        }

        $this->showDeleteModal = false;
        $this->deletingWebhookId = null;
        $this->dispatch('webhook-deleted');
    }

    public function reenableWebhook(int $webhookId): void
    {
        $webhook = Webhook::findOrFail($webhookId);

        $webhook->update([
            'is_active' => true,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ]);

        $this->dispatch('webhook-reenabled');
    }

    public function viewLogs(int $webhookId): void
    {
        $this->viewingLogsWebhookId = $webhookId;
        $this->showLogsModal = true;
    }

    public function closeLogsModal(): void
    {
        $this->showLogsModal = false;
        $this->viewingLogsWebhookId = null;
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="system" title="Webhooks" description="Manage webhook subscriptions for event notifications." :wide="true">
        <x-slot:actions>
            <flux:button variant="primary" wire:click="openCreateModal">Create Webhook</flux:button>
        </x-slot:actions>

        {{-- Secret Display Banner (shown once after creation) --}}
        @if ($showSecret)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Webhook created successfully</p>
                        <p class="mt-1 text-xs text-green-700 dark:text-green-300">Copy this secret now. It will not be shown again.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code class="block flex-1 rounded bg-green-100 px-3 py-2 font-mono text-sm text-green-900 dark:bg-green-900 dark:text-green-100 break-all">{{ $plainSecret }}</code>
                            <button
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js($plainSecret)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="s-btn s-btn-sm s-btn-ghost shrink-0"
                                title="Copy to clipboard"
                            >
                                <template x-if="!copied"><flux:icon.clipboard class="w-4 h-4" /></template>
                                <template x-if="copied"><flux:icon.check class="w-4 h-4 text-green-600" /></template>
                            </button>
                        </div>
                        <div class="mt-2">
                            <flux:button variant="ghost" size="sm" wire:click="dismissSecret">Dismiss</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Webhooks Table --}}
        @if ($webhooks->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Failures</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($webhooks as $webhook)
                            <tr wire:key="webhook-{{ $webhook->id }}">
                                <td class="font-medium max-w-xs truncate" title="{{ $webhook->url }}">{{ $webhook->url }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($webhook->events as $event)
                                            <span class="s-badge">{{ $event }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    @if ($webhook->is_active)
                                        <span class="s-status s-status-green">Active</span>
                                    @else
                                        <span class="s-status s-status-red">Disabled</span>
                                    @endif
                                </td>
                                <td class="text-sm text-zinc-500">{{ $webhook->consecutive_failures }}</td>
                                <td class="text-sm text-zinc-500">{{ $webhook->user?->name ?? 'Unknown' }}</td>
                                <td class="text-sm text-zinc-500">{{ $webhook->created_at->diffForHumans() }}</td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <button
                                            class="s-btn s-btn-ghost s-btn-sm"
                                            wire:click="viewLogs({{ $webhook->id }})"
                                            title="View delivery logs"
                                        >
                                            <flux:icon.document-text class="w-4 h-4" />
                                        </button>
                                        <button
                                            class="s-btn s-btn-ghost s-btn-sm"
                                            wire:click="openEditModal({{ $webhook->id }})"
                                            title="Edit webhook"
                                        >
                                            <flux:icon.pencil class="w-4 h-4" />
                                        </button>
                                        @unless ($webhook->is_active)
                                            <button
                                                class="s-btn s-btn-ghost s-btn-sm text-green-600 hover:text-green-700"
                                                wire:click="reenableWebhook({{ $webhook->id }})"
                                                title="Re-enable webhook"
                                            >
                                                <flux:icon.arrow-path class="w-4 h-4" />
                                            </button>
                                        @endunless
                                        <button
                                            class="s-btn s-btn-ghost s-btn-sm text-red-600 hover:text-red-700"
                                            wire:click="confirmDelete({{ $webhook->id }})"
                                            title="Delete webhook"
                                        >
                                            <flux:icon.trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:icon.signal class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No webhooks</p>
                <p class="mt-1 text-sm text-zinc-500">Create a webhook to receive event notifications at an external URL.</p>
            </div>
        @endif

        <x-action-message on="webhook-created">Webhook created successfully.</x-action-message>
        <x-action-message on="webhook-updated">Webhook updated successfully.</x-action-message>
        <x-action-message on="webhook-deleted">Webhook deleted.</x-action-message>
        <x-action-message on="webhook-reenabled">Webhook re-enabled.</x-action-message>

        {{-- Create Modal --}}
        <flux:modal wire:model="showCreateModal">
            <div class="space-y-6">
                <flux:heading size="lg">Create Webhook</flux:heading>

                <form wire:submit="createWebhook" class="space-y-4">
                    <flux:input wire:model="createUrl" label="Endpoint URL" placeholder="https://example.com/webhook" required />

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Events</label>
                        <div class="space-y-2">
                            @foreach ($availableEvents as $event)
                                <flux:checkbox wire:model="createEvents" value="{{ $event }}" label="{{ $event }}" wire:key="create-event-{{ $loop->index }}" />
                            @endforeach
                        </div>
                        @error('createEvents') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                        <flux:button variant="primary" type="submit">Create Webhook</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Edit Modal --}}
        <flux:modal wire:model="showEditModal">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Webhook</flux:heading>

                <form wire:submit="updateWebhook" class="space-y-4">
                    <flux:input wire:model="editUrl" label="Endpoint URL" placeholder="https://example.com/webhook" required />

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Events</label>
                        <div class="space-y-2">
                            @foreach ($availableEvents as $event)
                                <flux:checkbox wire:model="editEvents" value="{{ $event }}" label="{{ $event }}" wire:key="edit-event-{{ $loop->index }}" />
                            @endforeach
                        </div>
                        @error('editEvents') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <flux:checkbox wire:model="editIsActive" label="Active" />

                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                        <flux:button variant="primary" type="submit">Save Changes</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Delete Confirmation Modal --}}
        <flux:modal wire:model="showDeleteModal">
            <div class="space-y-4">
                <flux:heading size="lg">Delete Webhook</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete this webhook? Any pending deliveries will be cancelled and this action cannot be undone.
                </p>
                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                    <flux:button variant="danger" wire:click="deleteWebhook">Delete Webhook</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Logs Modal --}}
        <flux:modal wire:model="showLogsModal" class="max-w-3xl">
            <div class="space-y-4">
                <flux:heading size="lg">Delivery Logs</flux:heading>

                @if (is_countable($logs) && count($logs) > 0)
                    <div class="s-table-wrap max-h-96 overflow-y-auto">
                        <table class="s-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Response Code</th>
                                    <th>Attempts</th>
                                    <th>Delivered</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr wire:key="log-{{ $log->id }}">
                                        <td><span class="s-badge">{{ $log->event }}</span></td>
                                        <td>
                                            @if ($log->response_code && $log->response_code >= 200 && $log->response_code < 300)
                                                <span class="text-green-600 font-mono text-sm">{{ $log->response_code }}</span>
                                            @elseif ($log->response_code)
                                                <span class="text-red-600 font-mono text-sm">{{ $log->response_code }}</span>
                                            @else
                                                <span class="text-zinc-400 text-sm">--</span>
                                            @endif
                                        </td>
                                        <td class="text-sm text-zinc-500">{{ $log->attempts }}</td>
                                        <td>
                                            @if ($log->delivered_at)
                                                <span class="s-status s-status-green">Yes</span>
                                            @else
                                                <span class="s-status s-status-zinc">No</span>
                                            @endif
                                        </td>
                                        <td class="text-sm text-zinc-500">{{ $log->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-6 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                        <p class="text-sm text-zinc-500">No delivery logs yet.</p>
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="closeLogsModal">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-admin.layout>
</section>
