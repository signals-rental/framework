<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /** @var array<string, bool> */
    public array $modules = [];

    /** @var array<string, array{label: string, description: string, icon: string, locked: bool}> */
    protected array $moduleDefinitions = [
        'crm' => ['label' => 'CRM', 'description' => 'Contacts, organisations, and venues', 'icon' => 'user-group', 'locked' => true],
        'opportunities' => ['label' => 'Opportunities', 'description' => 'Quotes, orders, and active jobs', 'icon' => 'queue-list', 'locked' => false],
        'products' => ['label' => 'Products', 'description' => 'Product catalogue and rate cards', 'icon' => 'cube', 'locked' => false],
        'stock' => ['label' => 'Stock', 'description' => 'Inventory tracking and availability', 'icon' => 'archive-box', 'locked' => false],
        'invoicing' => ['label' => 'Invoicing', 'description' => 'Billing, payments, and credit notes', 'icon' => 'document-text', 'locked' => false],
        'crew' => ['label' => 'Crew', 'description' => 'Staff scheduling and assignments', 'icon' => 'users', 'locked' => false],
        'services' => ['label' => 'Services', 'description' => 'Labour and service items', 'icon' => 'wrench-screwdriver', 'locked' => false],
        'projects' => ['label' => 'Projects', 'description' => 'Multi-opportunity project management', 'icon' => 'folder', 'locked' => false],
        'inspections' => ['label' => 'Inspections', 'description' => 'Equipment testing and certifications', 'icon' => 'clipboard-document-check', 'locked' => false],
    ];

    public function mount(): void
    {
        foreach ($this->moduleDefinitions as $key => $def) {
            $this->modules[$key] = $def['locked'] ? true : (bool) settings("modules.{$key}", false);
        }
    }

    public function toggle(string $module): void
    {
        if (! array_key_exists($module, $this->moduleDefinitions)) {
            return;
        }

        if ($this->moduleDefinitions[$module]['locked']) {
            return;
        }

        $this->modules[$module] = ! $this->modules[$module];
        settings()->set("modules.{$module}", $this->modules[$module], 'boolean');
    }

    /**
     * @return array<string, array{label: string, description: string, icon: string, locked: bool}>
     */
    public function getModuleDefinitionsProperty(): array
    {
        return $this->moduleDefinitions;
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Modules" description="Enable or disable application modules.">
        <div class="s-module-grid">
            @foreach($this->moduleDefinitions as $key => $def)
                <div wire:key="module-{{ $key }}"
                     class="s-module-card {{ $modules[$key] ? 'enabled' : '' }} {{ $def['locked'] ? 'locked' : '' }}"
                     @unless($def['locked']) wire:click="toggle('{{ $key }}')" @endunless>
                    <div class="s-module-icon">
                        <flux:icon :name="$def['icon']" class="!size-5" />
                    </div>
                    <div class="s-module-info">
                        <div class="s-module-name">{{ $def['label'] }}</div>
                        <div class="s-module-desc">{{ $def['description'] }}</div>
                    </div>
                    @if($def['locked'])
                        <div class="s-module-lock">Always On</div>
                    @else
                        <div class="s-toggle {{ $modules[$key] ? 'on' : '' }}">
                            <div class="s-toggle-knob"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-admin.layout>
</section>
