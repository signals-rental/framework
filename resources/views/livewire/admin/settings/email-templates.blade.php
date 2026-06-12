<?php

use App\Actions\Admin\SendTestTemplateEmail;
use App\Models\EmailTemplate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Email Templates')] class extends Component {
    public bool $showTestModal = false;

    public ?int $testTemplateId = null;

    public string $testRecipient = '';

    /** Bumped on each modal open so the form re-renders fresh — a morph alone leaves the native select displaying its previous choice after the bound property is reset. */
    public int $testModalNonce = 0;

    public function openTestModal(): void
    {
        $this->reset(['testTemplateId', 'testRecipient']);
        $this->resetErrorBag();
        $this->testModalNonce++;
        $this->showTestModal = true;
    }

    public function sendTest(): void
    {
        $this->validate([
            'testTemplateId' => ['required', 'integer', 'exists:email_templates,id'],
            'testRecipient' => ['required', 'email:filter'],
        ], [
            'testTemplateId.required' => 'Choose a template to send.',
        ]);

        $template = EmailTemplate::findOrFail($this->testTemplateId);

        try {
            (new SendTestTemplateEmail)($template, $this->testRecipient);
        } catch (\Throwable $e) {
            $this->addError('testRecipient', 'Failed to send test email: '.$e->getMessage());

            return;
        }

        $this->showTestModal = false;
        $this->reset(['testTemplateId', 'testRecipient']);
        $this->dispatch('test-template-sent');
    }

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
        <x-slot:actions>
            <flux:button variant="primary" wire:click="openTestModal" icon="paper-airplane">Send Test</flux:button>
        </x-slot:actions>

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

        <x-action-message on="test-template-sent">Test email sent.</x-action-message>

        <flux:modal wire:model.self="showTestModal" class="md:w-96">
            <form wire:submit="sendTest" wire:key="send-test-form-{{ $testModalNonce }}" class="space-y-4">
                <div>
                    <flux:heading size="lg">Send Test Email</flux:heading>
                    <flux:subheading>Render a template with sample data and send it to an address.</flux:subheading>
                </div>

                <flux:select wire:model.live="testTemplateId" label="Template">
                    <flux:select.option value="">Choose a template...</flux:select.option>
                    @foreach ($templates as $template)
                        <flux:select.option value="{{ $template->id }}">{{ $template->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="testRecipient" label="Recipient Email" type="email" placeholder="you@example.com" />

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('showTestModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Send Test</flux:button>
                </div>
            </form>
        </flux:modal>
    </x-admin.layout>
</section>
