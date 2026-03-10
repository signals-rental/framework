<?php

use App\Actions\Admin\SendTestEmail;
use App\Services\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $mailer = 'log';
    public string $smtpHost = '';
    public int $smtpPort = 587;
    public string $smtpUsername = '';
    public string $smtpPassword = '';
    public string $smtpEncryption = 'tls';
    public string $sesKey = '';
    public string $sesSecret = '';
    public string $sesRegion = 'eu-west-1';
    public string $mailgunDomain = '';
    public string $mailgunSecret = '';
    public string $postmarkToken = '';
    public string $fromAddress = '';
    public string $fromName = '';
    public string $replyToAddress = '';
    public string $testEmailAddress = '';

    public function mount(): void
    {
        $group = settings()->group('email');

        $this->mailer = (string) $group['mailer'];
        $this->smtpHost = (string) $group['smtp_host'];
        $this->smtpPort = (int) $group['smtp_port'];
        $this->smtpUsername = (string) $group['smtp_username'];
        $this->smtpPassword = (string) $group['smtp_password'];
        $this->smtpEncryption = (string) $group['smtp_encryption'];
        $this->sesKey = (string) $group['ses_key'];
        $this->sesSecret = (string) $group['ses_secret'];
        $this->sesRegion = (string) $group['ses_region'];
        $this->mailgunDomain = (string) $group['mailgun_domain'];
        $this->mailgunSecret = (string) $group['mailgun_secret'];
        $this->postmarkToken = (string) $group['postmark_token'];
        $this->fromAddress = (string) $group['from_address'];
        $this->fromName = (string) $group['from_name'];
        $this->replyToAddress = (string) $group['reply_to_address'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('email');

        $validated = $this->validate([
            'mailer' => $rules['mailer'],
            'smtpHost' => $rules['smtp_host'],
            'smtpPort' => $rules['smtp_port'],
            'smtpUsername' => $rules['smtp_username'],
            'smtpPassword' => $rules['smtp_password'],
            'smtpEncryption' => $rules['smtp_encryption'],
            'sesKey' => $rules['ses_key'],
            'sesSecret' => $rules['ses_secret'],
            'sesRegion' => $rules['ses_region'],
            'mailgunDomain' => $rules['mailgun_domain'],
            'mailgunSecret' => $rules['mailgun_secret'],
            'postmarkToken' => $rules['postmark_token'],
            'fromAddress' => $rules['from_address'],
            'fromName' => $rules['from_name'],
            'replyToAddress' => $rules['reply_to_address'],
        ]);

        $types = $registry->types('email');

        settings()->setMany([
            'email.mailer' => $validated['mailer'],
            'email.smtp_host' => $validated['smtpHost'],
            'email.smtp_port' => ['value' => $validated['smtpPort'], 'type' => $types['smtp_port'] ?? 'string'],
            'email.smtp_username' => $validated['smtpUsername'],
            'email.smtp_password' => ['value' => $validated['smtpPassword'], 'type' => $types['smtp_password'] ?? 'string'],
            'email.smtp_encryption' => $validated['smtpEncryption'],
            'email.ses_key' => $validated['sesKey'],
            'email.ses_secret' => ['value' => $validated['sesSecret'], 'type' => $types['ses_secret'] ?? 'string'],
            'email.ses_region' => $validated['sesRegion'],
            'email.mailgun_domain' => $validated['mailgunDomain'],
            'email.mailgun_secret' => ['value' => $validated['mailgunSecret'], 'type' => $types['mailgun_secret'] ?? 'string'],
            'email.postmark_token' => ['value' => $validated['postmarkToken'], 'type' => $types['postmark_token'] ?? 'string'],
            'email.from_address' => $validated['fromAddress'],
            'email.from_name' => $validated['fromName'],
            'email.reply_to_address' => $validated['replyToAddress'],
        ]);

        $this->dispatch('email-settings-saved');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'testEmailAddress' => ['required', 'email'],
        ]);

        try {
            (new SendTestEmail)($this->testEmailAddress);
            $this->dispatch('test-email-sent');
        } catch (\Exception $e) {
            $this->addError('testEmailAddress', 'Failed to send test email: '.$e->getMessage());
        }
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Email" description="Configure how your application sends email.">
        <x-signals.form-section title="Mail Provider">
            <form wire:submit="save" class="space-y-6">
                <flux:select wire:model.live="mailer" label="Mail Driver">
                    <option value="log">Log (development only)</option>
                    <option value="smtp">SMTP</option>
                    <option value="ses">Amazon SES</option>
                    <option value="mailgun">Mailgun</option>
                    <option value="postmark">Postmark</option>
                </flux:select>

                {{-- SMTP Settings --}}
                <div x-show="$wire.mailer === 'smtp'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="smtpHost" label="SMTP Host" placeholder="smtp.example.com" />
                        <flux:input wire:model="smtpPort" label="SMTP Port" type="number" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="smtpUsername" label="Username" />
                        <flux:input wire:model="smtpPassword" label="Password" type="password" />
                    </div>
                    <flux:select wire:model="smtpEncryption" label="Encryption">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                    </flux:select>
                </div>

                {{-- SES Settings --}}
                <div x-show="$wire.mailer === 'ses'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="sesKey" label="AWS Access Key" />
                        <flux:input wire:model="sesSecret" label="AWS Secret Key" type="password" />
                    </div>
                    <flux:input wire:model="sesRegion" label="AWS Region" placeholder="eu-west-1" />
                </div>

                {{-- Mailgun Settings --}}
                <div x-show="$wire.mailer === 'mailgun'" x-cloak class="space-y-4">
                    <flux:input wire:model="mailgunDomain" label="Mailgun Domain" placeholder="mg.example.com" />
                    <flux:input wire:model="mailgunSecret" label="Mailgun Secret" type="password" />
                </div>

                {{-- Postmark Settings --}}
                <div x-show="$wire.mailer === 'postmark'" x-cloak class="space-y-4">
                    <flux:input wire:model="postmarkToken" label="Postmark Server Token" type="password" />
                </div>

                <hr class="border-[var(--card-border)]" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="fromAddress" label="From Address" type="email" placeholder="noreply@example.com" />
                    <flux:input wire:model="fromName" label="From Name" placeholder="My Company" />
                </div>

                <flux:input wire:model="replyToAddress" label="Reply-To Address" type="email" placeholder="support@example.com" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>

                    <x-action-message on="email-settings-saved">
                        Saved.
                    </x-action-message>
                </div>
            </form>
        </x-signals.form-section>

        <x-signals.form-section title="Test Email">
            <form wire:submit="sendTestEmail" class="space-y-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Send a test email to verify your mail configuration is working correctly. Save your settings first.
                </p>

                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <flux:input wire:model="testEmailAddress" label="Recipient Email" type="email" placeholder="you@example.com" />
                    </div>
                    <flux:button type="submit" variant="filled">Send Test Email</flux:button>
                </div>

                <x-action-message on="test-email-sent">
                    Test email sent successfully.
                </x-action-message>
            </form>
        </x-signals.form-section>
    </x-admin.layout>
</section>
