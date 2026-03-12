<?php

use App\Services\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Preferences')] class extends Component {
    public string $numberDecimalSeparator = '.';
    public string $numberThousandsSeparator = ',';
    public string $currencyDisplay = 'symbol';
    public int $firstDayOfWeek = 1;
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $group = settings()->group('preferences');

        $this->numberDecimalSeparator = $group['number_decimal_separator'];
        $this->numberThousandsSeparator = $group['number_thousands_separator'];
        $this->currencyDisplay = $group['currency_display'];
        $this->firstDayOfWeek = (int) $group['first_day_of_week'];
        $this->itemsPerPage = (int) $group['items_per_page'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('preferences');
        $types = $registry->types('preferences');

        $validated = $this->validate([
            'numberDecimalSeparator' => $rules['number_decimal_separator'],
            'numberThousandsSeparator' => $rules['number_thousands_separator'],
            'currencyDisplay' => $rules['currency_display'],
            'firstDayOfWeek' => $rules['first_day_of_week'],
            'itemsPerPage' => $rules['items_per_page'],
        ]);

        $settings = [
            'preferences.number_decimal_separator' => $validated['numberDecimalSeparator'],
            'preferences.number_thousands_separator' => $validated['numberThousandsSeparator'],
            'preferences.currency_display' => $validated['currencyDisplay'],
            'preferences.first_day_of_week' => ['value' => $validated['firstDayOfWeek'], 'type' => $types['first_day_of_week']],
            'preferences.items_per_page' => ['value' => $validated['itemsPerPage'], 'type' => $types['items_per_page']],
        ];

        settings()->setMany($settings);

        $this->dispatch('preferences-settings-saved');
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="General" description="Configure number formatting and display preferences.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Formatting">
                <div class="space-y-4">
                    <flux:select wire:model="firstDayOfWeek" label="First Day of Week">
                        <flux:select.option value="0">Sunday</flux:select.option>
                        <flux:select.option value="1">Monday</flux:select.option>
                        <flux:select.option value="6">Saturday</flux:select.option>
                    </flux:select>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Number Formatting">
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="numberDecimalSeparator" label="Decimal Separator">
                        <flux:select.option value=".">Period (.)</flux:select.option>
                        <flux:select.option value=",">Comma (,)</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="numberThousandsSeparator" label="Thousands Separator">
                        <flux:select.option value=",">Comma (1,000)</flux:select.option>
                        <flux:select.option value=".">Period (1.000)</flux:select.option>
                        <flux:select.option value=" ">Space (1 000)</flux:select.option>
                        <flux:select.option value="">None (1000)</flux:select.option>
                    </flux:select>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Display">
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="currencyDisplay" label="Currency Display">
                        <flux:select.option value="symbol">Symbol ($, &pound;, &euro;)</flux:select.option>
                        <flux:select.option value="code">Code (USD, GBP, EUR)</flux:select.option>
                        <flux:select.option value="name">Name (US Dollar)</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="itemsPerPage" label="Items Per Page">
                        <flux:select.option value="10">10</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="preferences-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
