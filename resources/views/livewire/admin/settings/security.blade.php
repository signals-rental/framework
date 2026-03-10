<?php

use App\Services\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public int $passwordMinLength = 8;
    public bool $passwordRequireUppercase = false;
    public bool $passwordRequireNumber = false;
    public bool $passwordRequireSpecial = false;
    public int $sessionTimeout = 120;
    public int $maxLoginAttempts = 5;
    public int $lockoutDuration = 15;
    public bool $require2faAdmin = false;
    public bool $require2faAll = false;

    public function mount(): void
    {
        $group = settings()->group('security');

        $this->passwordMinLength = (int) $group['password_min_length'];
        $this->passwordRequireUppercase = (bool) $group['password_require_uppercase'];
        $this->passwordRequireNumber = (bool) $group['password_require_number'];
        $this->passwordRequireSpecial = (bool) $group['password_require_special'];
        $this->sessionTimeout = (int) $group['session_timeout'];
        $this->maxLoginAttempts = (int) $group['max_login_attempts'];
        $this->lockoutDuration = (int) $group['lockout_duration'];
        $this->require2faAdmin = (bool) $group['require_2fa_admin'];
        $this->require2faAll = (bool) $group['require_2fa_all'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('security');
        $types = $registry->types('security');

        $validated = $this->validate([
            'passwordMinLength' => $rules['password_min_length'],
            'passwordRequireUppercase' => $rules['password_require_uppercase'],
            'passwordRequireNumber' => $rules['password_require_number'],
            'passwordRequireSpecial' => $rules['password_require_special'],
            'sessionTimeout' => $rules['session_timeout'],
            'maxLoginAttempts' => $rules['max_login_attempts'],
            'lockoutDuration' => $rules['lockout_duration'],
            'require2faAdmin' => $rules['require_2fa_admin'],
            'require2faAll' => $rules['require_2fa_all'],
        ]);

        settings()->setMany([
            'security.password_min_length' => ['value' => $validated['passwordMinLength'], 'type' => $types['password_min_length']],
            'security.password_require_uppercase' => ['value' => $validated['passwordRequireUppercase'], 'type' => $types['password_require_uppercase']],
            'security.password_require_number' => ['value' => $validated['passwordRequireNumber'], 'type' => $types['password_require_number']],
            'security.password_require_special' => ['value' => $validated['passwordRequireSpecial'], 'type' => $types['password_require_special']],
            'security.session_timeout' => ['value' => $validated['sessionTimeout'], 'type' => $types['session_timeout']],
            'security.max_login_attempts' => ['value' => $validated['maxLoginAttempts'], 'type' => $types['max_login_attempts']],
            'security.lockout_duration' => ['value' => $validated['lockoutDuration'], 'type' => $types['lockout_duration']],
            'security.require_2fa_admin' => ['value' => $validated['require2faAdmin'], 'type' => $types['require_2fa_admin']],
            'security.require_2fa_all' => ['value' => $validated['require2faAll'], 'type' => $types['require_2fa_all']],
        ]);

        $this->dispatch('security-settings-saved');
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="users" title="Security" description="Configure password policies, session settings, and two-factor authentication.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Password Policy">
                <div class="space-y-4">
                    <flux:input wire:model="passwordMinLength" label="Minimum Password Length" type="number" min="6" max="128" />

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($passwordRequireUppercase) }">
                            <input type="checkbox" wire:model="passwordRequireUppercase" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">Require uppercase letter</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($passwordRequireNumber) }">
                            <input type="checkbox" wire:model="passwordRequireNumber" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">Require number</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($passwordRequireSpecial) }">
                            <input type="checkbox" wire:model="passwordRequireSpecial" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">Require special character</span>
                        </label>
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Login Security">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="maxLoginAttempts" label="Max Login Attempts" type="number" min="1" max="100" />
                    <flux:input wire:model="lockoutDuration" label="Lockout Duration (minutes)" type="number" min="1" max="1440" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Session">
                <flux:input wire:model="sessionTimeout" label="Session Timeout (minutes)" type="number" min="5" max="1440" />
                <p class="text-xs text-zinc-500 mt-1">Inactive sessions will expire after this duration.</p>
            </x-signals.form-section>

            <x-signals.form-section title="Two-Factor Authentication">
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($require2faAdmin) }">
                        <input type="checkbox" wire:model="require2faAdmin" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Require 2FA for admin users</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($require2faAll) }">
                        <input type="checkbox" wire:model="require2faAll" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Require 2FA for all users</span>
                    </label>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="security-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
