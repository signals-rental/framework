<?php

use App\Models\NotificationSetting;
use App\Models\NotificationType;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function toggleChannel(int $typeId, string $channel): void
    {
        Gate::authorize('notifications.manage');

        $type = NotificationType::findOrFail($typeId);
        $setting = NotificationSetting::firstOrCreate(
            ['notification_type_id' => $type->id],
            ['channels' => $type->default_channels, 'is_enabled' => true],
        );

        $channels = $setting->channels ?? $type->default_channels;

        if (in_array($channel, $channels)) {
            $channels = array_values(array_diff($channels, [$channel]));
        } else {
            $channels[] = $channel;
        }

        $setting->update(['channels' => $channels]);

        $this->dispatch('$refresh');
    }

    public function toggleEnabled(int $typeId): void
    {
        Gate::authorize('notifications.manage');

        $type = NotificationType::findOrFail($typeId);
        $setting = NotificationSetting::firstOrCreate(
            ['notification_type_id' => $type->id],
            ['channels' => $type->default_channels, 'is_enabled' => true],
        );

        $setting->update(['is_enabled' => ! $setting->is_enabled]);

        $this->dispatch('$refresh');
    }

    public function with(): array
    {
        $types = NotificationType::query()
            ->with('setting')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $grouped = $types->groupBy('category');

        $allChannels = ['database', 'mail', 'broadcast'];

        return [
            'grouped' => $grouped,
            'allChannels' => $allChannels,
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Notifications" description="Configure system-wide notification channels for each notification type.">
        @if ($grouped->isEmpty())
            <div class="s-card p-8 text-center text-zinc-500 dark:text-zinc-400">
                <p>No notification types found. Run database seeders to create default notification types.</p>
            </div>
        @else
            @foreach ($grouped as $category => $types)
                <div class="s-card mb-4">
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $category }}</h3>
                    </div>

                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Notification</th>
                                @foreach ($allChannels as $channel)
                                    <th class="w-24 text-center">{{ ucfirst($channel) }}</th>
                                @endforeach
                                <th class="w-24 text-center">Enabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($types as $type)
                                @php
                                    $setting = $type->setting;
                                    $effectiveChannels = $type->effectiveChannels();
                                    $isEnabled = ! $setting || $setting->is_enabled;
                                @endphp
                                <tr wire:key="type-{{ $type->id }}">
                                    <td>
                                        <div class="font-medium text-sm">{{ $type->name }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $type->description }}</div>
                                    </td>
                                    @foreach ($allChannels as $channel)
                                        <td class="text-center">
                                            @if (in_array($channel, $type->available_channels))
                                                <flux:switch
                                                    wire:click="toggleChannel({{ $type->id }}, '{{ $channel }}')"
                                                    :checked="in_array($channel, $effectiveChannels)"
                                                    :disabled="! $isEnabled"
                                                />
                                            @else
                                                <span class="text-xs text-zinc-400">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center">
                                        <flux:switch
                                            wire:click="toggleEnabled({{ $type->id }})"
                                            :checked="$isEnabled"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </x-admin.layout>
</section>
