<?php

use App\Data\Reference\CountryData;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Company Details')] class extends Component {
    public string $name = '';
    public string $countryCode = '';
    public string $timezone = '';
    public string $currency = '';
    public string $taxRate = '';
    public string $taxLabel = '';
    public string $dateFormat = '';
    public string $timeFormat = '';
    public int $fiscalYearStart = 1;

    public function mount(): void
    {
        $this->name = (string) settings('company.name', '');
        $this->countryCode = (string) settings('company.country_code', '');
        $this->timezone = (string) settings('company.timezone', '');
        $this->currency = (string) settings('company.currency', '');
        $this->taxRate = (string) settings('company.tax_rate', '');
        $this->taxLabel = (string) settings('company.tax_label', '');
        $this->dateFormat = (string) settings('company.date_format', '');
        $this->timeFormat = (string) settings('company.time_format', '');
        $this->fiscalYearStart = (int) settings('company.fiscal_year_start', 1);
    }

    public function updatedCountryCode(string $value): void
    {
        $defaults = CountryData::defaults($value);

        if ($defaults) {
            $this->timezone = $defaults['timezone'];
            $this->currency = $defaults['currency'];
            $this->taxRate = $defaults['tax_rate'];
            $this->taxLabel = $defaults['tax_label'];
            $this->dateFormat = $defaults['date_format'];
            $this->timeFormat = $defaults['time_format'];
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'countryCode' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'taxLabel' => ['required', 'string', 'max:50'],
            'dateFormat' => ['required', 'string', 'max:20'],
            'timeFormat' => ['required', 'string', 'max:20'],
            'fiscalYearStart' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        settings()->setMany([
            'company.name' => $validated['name'],
            'company.country_code' => $validated['countryCode'],
            'company.timezone' => $validated['timezone'],
            'company.currency' => $validated['currency'],
            'company.tax_rate' => $validated['taxRate'],
            'company.tax_label' => $validated['taxLabel'],
            'company.date_format' => $validated['dateFormat'],
            'company.time_format' => $validated['timeFormat'],
            'company.fiscal_year_start' => ['value' => $validated['fiscalYearStart'], 'type' => 'integer'],
        ]);

        $this->dispatch('company-settings-saved');
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Company Details" description="Manage your company information, locale, and tax settings.">
        <x-signals.form-section title="Company Information">
            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="name" label="Company Name" required />

                <flux:select wire:model.live="countryCode" label="Country" required>
                    <option value="">Select country...</option>
                    @foreach(CountryData::options() as $code => $countryName)
                        <option value="{{ $code }}">{{ $countryName }}</option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="timezone" label="Timezone" required>
                        <option value="">Select timezone...</option>
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="currency" label="Currency" required>
                        <option value="">Select currency...</option>
                        @foreach(['GBP','USD','EUR','CAD','AUD','NZD','CHF','SEK','NOK','DKK','ZAR','AED','SGD','JPY','INR'] as $cur)
                            <option value="{{ $cur }}">{{ $cur }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="taxRate" label="Default Tax Rate (%)" type="number" step="0.01" required />
                    <flux:input wire:model="taxLabel" label="Tax Label" placeholder="e.g. VAT, GST" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="dateFormat" label="Date Format" required>
                        @foreach(['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd.m.Y' => 'DD.MM.YYYY', 'd-m-Y' => 'DD-MM-YYYY', 'Y/m/d' => 'YYYY/MM/DD'] as $fmt => $label)
                            <option value="{{ $fmt }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="timeFormat" label="Time Format" required>
                        <option value="H:i">24-hour (14:30)</option>
                        <option value="g:i A">12-hour (2:30 PM)</option>
                        <option value="h:i A">12-hour (02:30 PM)</option>
                    </flux:select>
                </div>

                <flux:select wire:model="fiscalYearStart" label="Fiscal Year Start">
                    @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $month)
                        <option value="{{ $i + 1 }}">{{ $month }}</option>
                    @endforeach
                </flux:select>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>

                    <x-action-message on="company-settings-saved">
                        Saved.
                    </x-action-message>
                </div>
            </form>
        </x-signals.form-section>
    </x-admin.layout>
</section>
