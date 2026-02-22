<div>
    @unless ($dismissed)
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Getting Started</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Complete these steps to set up your rental system.</p>
                </div>
                <flux:button wire:click="dismiss" variant="ghost" size="sm">
                    Dismiss
                </flux:button>
            </div>

            {{-- Progress bar --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $progress }}% complete</span>
                    <span class="text-zinc-400 dark:text-zinc-500">{{ collect($items)->where('completed', true)->count() }} of {{ count($items) }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>
            </div>

            {{-- Checklist items --}}
            <div class="mt-5 flex flex-col gap-3">
                @foreach ($items as $item)
                    <div class="flex items-start gap-3">
                        @if ($item['completed'])
                            <flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                        @else
                            <div class="mt-0.5 size-5 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></div>
                        @endif
                        <div>
                            <div @class([
                                'text-sm font-medium',
                                'text-zinc-900 dark:text-zinc-100' => ! $item['completed'],
                                'text-zinc-400 line-through dark:text-zinc-500' => $item['completed'],
                            ])>
                                {{ $item['label'] }}
                            </div>
                            @unless ($item['completed'])
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item['description'] }}</div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endunless
</div>
