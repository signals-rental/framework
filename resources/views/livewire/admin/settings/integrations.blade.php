<?php

use App\Services\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Integrations')] class extends Component {
    public string $what3wordsApiKey = '';
    public string $googleMapsApiKey = '';

    public function mount(): void
    {
        $group = settings()->group('integrations');

        $this->what3wordsApiKey = (string) $group['what3words_api_key'];
        $this->googleMapsApiKey = (string) $group['google_maps_api_key'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('integrations');

        $validated = $this->validate([
            'what3wordsApiKey' => $rules['what3words_api_key'],
            'googleMapsApiKey' => $rules['google_maps_api_key'],
        ]);

        $types = $registry->types('integrations');

        settings()->setMany([
            'integrations.what3words_api_key' => ['value' => $validated['what3wordsApiKey'], 'type' => $types['what3words_api_key'] ?? 'string'],
            'integrations.google_maps_api_key' => ['value' => $validated['googleMapsApiKey'], 'type' => $types['google_maps_api_key'] ?? 'string'],
        ]);

        $this->dispatch('integration-settings-saved');
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Integrations" description="Configure API keys for third-party services.">
        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Geocoding">
                <div class="space-y-4">
                    <flux:input wire:model="what3wordsApiKey" label="what3words API Key" type="password" placeholder="Enter your what3words API key" description="Used for precise address geocoding. Get a key at developer.what3words.com" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Maps">
                <div class="space-y-4">
                    <flux:input wire:model="googleMapsApiKey" label="Google Maps API Key" type="password" placeholder="Enter your Google Maps API key" description="Used for address autocomplete and map display." />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
                <x-action-message on="integration-settings-saved">Saved.</x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
