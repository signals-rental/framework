<?php

use App\Models\EmailTemplate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        return [
            'templates' => EmailTemplate::query()
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Email Templates" description="Manage the content and layout of system emails.">
        @if ($templates->isEmpty())
            <div class="s-card p-8 text-center text-zinc-500 dark:text-zinc-400">
                <p>No email templates found. Run database seeders to create default templates.</p>
            </div>
        @else
            <div class="s-card overflow-hidden">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="w-20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr wire:key="template-{{ $template->id }}">
                                <td class="font-medium">{{ $template->name }}</td>
                                <td class="text-xs font-mono text-zinc-500">{{ $template->key }}</td>
                                <td class="text-sm">{{ Str::limit($template->subject, 50) }}</td>
                                <td>
                                    @if ($template->is_system)
                                        <span class="s-badge bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">System</span>
                                    @else
                                        <span class="s-badge">Custom</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($template->is_active)
                                        <span class="s-badge bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                    @else
                                        <span class="s-badge bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.settings.email-templates.edit', $template) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.layout>
</section>
