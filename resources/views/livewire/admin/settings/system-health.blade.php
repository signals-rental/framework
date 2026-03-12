<?php

use App\Services\SystemHealthService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('System Health')] class extends Component {
    /** @var array<int, array{name: string, status: string, details: array<string, mixed>}> */
    public array $checks = [];

    public function mount(): void
    {
        $this->runChecks();
    }

    public function refresh(): void
    {
        $this->runChecks();
    }

    private function runChecks(): void
    {
        $this->checks = app(SystemHealthService::class)->check();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="system" title="System Health" description="Real-time diagnostic overview of connected services and system status.">
        <div class="mb-6">
            <flux:button wire:click="refresh" variant="primary" size="sm">
                <span wire:loading.remove wire:target="refresh">Refresh</span>
                <span wire:loading wire:target="refresh">Checking...</span>
            </flux:button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($checks as $check)
                <div class="s-card p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $check['name'] }}</h3>
                        @if ($check['status'] === 'ok')
                            <span class="s-badge bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Healthy</span>
                        @elseif ($check['status'] === 'warning')
                            <span class="s-badge bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Warning</span>
                        @elseif ($check['status'] === 'error')
                            <span class="s-badge bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Error</span>
                        @elseif ($check['status'] === 'skipped')
                            <span class="s-badge bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Skipped</span>
                        @endif
                    </div>

                    <dl class="space-y-1.5 text-xs">
                        @foreach ($check['details'] as $key => $value)
                            @if ($value !== null && $value !== '')
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-500 dark:text-zinc-400 shrink-0">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100 font-mono text-right break-words min-w-0">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </x-admin.layout>
</section>
