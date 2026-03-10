<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="s-auth-heading">Company Details</h1>
        <p class="s-auth-description">Tell us about your business. Select your country to auto-fill regional defaults.</p>
    </div>

    <flux:select wire:model.live="countryCode" label="Country" placeholder="Select your country">
        @foreach ($this->countryOptions() as $code => $name)
            <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="companyName" label="Company Name" placeholder="Acme Rental Co." required />

    <div class="grid grid-cols-2 gap-4">
        <flux:input wire:model="timezone" label="Timezone" placeholder="Europe/London" required />
        <flux:input wire:model="currency" label="Currency" placeholder="GBP" maxlength="3" required />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <flux:input wire:model="taxRate" label="Tax Rate (%)" type="number" step="0.01" min="0" max="100" placeholder="20.00" required />
        <flux:input wire:model="taxLabel" label="Tax Label" placeholder="VAT" required />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <flux:input wire:model="dateFormat" label="Date Format" placeholder="d/m/Y" required />
        <flux:input wire:model="timeFormat" label="Time Format" placeholder="H:i" required />
    </div>

    <flux:select wire:model="fiscalYearStart" label="Fiscal Year Start">
        <flux:select.option value="1">January</flux:select.option>
        <flux:select.option value="2">February</flux:select.option>
        <flux:select.option value="3">March</flux:select.option>
        <flux:select.option value="4">April</flux:select.option>
        <flux:select.option value="5">May</flux:select.option>
        <flux:select.option value="6">June</flux:select.option>
        <flux:select.option value="7">July</flux:select.option>
        <flux:select.option value="8">August</flux:select.option>
        <flux:select.option value="9">September</flux:select.option>
        <flux:select.option value="10">October</flux:select.option>
        <flux:select.option value="11">November</flux:select.option>
        <flux:select.option value="12">December</flux:select.option>
    </flux:select>
</div>
