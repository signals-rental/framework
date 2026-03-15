<?php

use App\Actions\Setup\CheckInfrastructure;
use App\Actions\Setup\CompleteSetup;
use App\Data\Reference\CountryData;
use App\Data\Setup\CompleteSetupData;
use App\Enums\FeatureProfile;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.setup')] class extends Component {
    use WithFileUploads;

    public int $currentStep = 1;

    /** @var array<string, array{passed: bool, message: string}> */
    public array $infrastructureChecks = [];

    public bool $infrastructurePassed = false;

    // Step 1: Company
    public string $countryCode = '';
    public string $companyName = '';
    public string $timezone = '';
    public string $currency = '';
    public string $taxRate = '';
    public string $taxLabel = '';
    public string $dateFormat = '';
    public string $timeFormat = '';
    public int $fiscalYearStart = 1;

    // Step 2: Stores
    /** @var array<int, array{name: string, street: string, city: string, county: string, postcode: string, country_code: string, is_default: bool}> */
    public array $stores = [];

    // Step 3: Profile
    public string $profile = 'general';

    // Step 4: Branding
    public string $primaryColour = '#1e3a5f';
    public string $accentColour = '#3b82f6';
    public $logo = null;

    // Step 5: Admin
    public string $adminName = '';
    public string $adminEmail = '';
    public string $adminPassword = '';
    public string $adminPassword_confirmation = '';

    public function mount(): void
    {
        $result = app(CheckInfrastructure::class)();
        $this->infrastructureChecks = $result['checks'];
        $this->infrastructurePassed = $result['passed'];

        $this->addStore();
    }

    public function retryInfrastructureChecks(): void
    {
        $result = app(CheckInfrastructure::class)();
        $this->infrastructureChecks = $result['checks'];
        $this->infrastructurePassed = $result['passed'];
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

            foreach ($this->stores as $i => $store) {
                if (empty($store['country_code'])) {
                    $this->stores[$i]['country_code'] = $value;
                }
            }
        }
    }

    public function addStore(): void
    {
        $this->stores[] = [
            'name' => '',
            'street' => '',
            'city' => '',
            'county' => '',
            'postcode' => '',
            'country_code' => $this->countryCode,
            'is_default' => count($this->stores) === 0,
        ];
    }

    public function removeStore(int $index): void
    {
        if (count($this->stores) <= 1) {
            return;
        }

        $wasDefault = $this->stores[$index]['is_default'] ?? false;
        unset($this->stores[$index]);
        $this->stores = array_values($this->stores);

        if ($wasDefault && count($this->stores) > 0) {
            $this->stores[0]['is_default'] = true;
        }
    }

    public function setDefaultStore(int $index): void
    {
        foreach ($this->stores as $i => $store) {
            $this->stores[$i]['is_default'] = ($i === $index);
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->currentStep);
        $this->currentStep = min($this->currentStep + 1, 6);
    }

    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function completeSetup(): void
    {
        $this->validateStep(5);

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('branding', 'public');
        }

        $data = new CompleteSetupData(
            companyName: $this->companyName,
            countryCode: $this->countryCode,
            timezone: $this->timezone,
            currency: $this->currency,
            taxRate: $this->taxRate,
            taxLabel: $this->taxLabel,
            dateFormat: $this->dateFormat,
            timeFormat: $this->timeFormat,
            fiscalYearStart: $this->fiscalYearStart,
            profile: FeatureProfile::from($this->profile),
            stores: $this->stores,
            primaryColour: $this->primaryColour,
            accentColour: $this->accentColour,
            logoPath: $logoPath,
            adminName: $this->adminName,
            adminEmail: $this->adminEmail,
            adminPassword: $this->adminPassword,
        );

        $user = (new CompleteSetup)($data);

        auth()->login($user);

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function countryOptions(): array
    {
        return CountryData::options();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function profileOptions(): array
    {
        $options = [];
        foreach (FeatureProfile::cases() as $profile) {
            $options[$profile->value] = [
                'label' => $profile->label(),
                'description' => $profile->description(),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, bool>
     */
    public function selectedProfileModules(): array
    {
        $profile = FeatureProfile::tryFrom($this->profile);

        return $profile ? $profile->modules() : FeatureProfile::General->modules();
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'countryCode' => ['required', 'string', 'size:2'],
                'companyName' => ['required', 'string', 'max:255'],
                'timezone' => ['required', 'string', 'max:100'],
                'currency' => ['required', 'string', 'size:3'],
                'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
                'taxLabel' => ['required', 'string', 'max:50'],
                'dateFormat' => ['required', 'string', 'max:20'],
                'timeFormat' => ['required', 'string', 'max:20'],
                'fiscalYearStart' => ['required', 'integer', 'min:1', 'max:12'],
            ]),
            2 => $this->validate([
                'stores' => ['required', 'array', 'min:1'],
                'stores.*.name' => ['required', 'string', 'max:255'],
            ]),
            3 => $this->validate([
                'profile' => ['required', 'string', 'in:' . implode(',', array_column(FeatureProfile::cases(), 'value'))],
            ]),
            4 => $this->validate([
                'primaryColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'accentColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'logo' => ['nullable', 'image', 'max:2048'],
            ]),
            5 => $this->validate([
                'adminName' => ['required', 'string', 'max:255'],
                'adminEmail' => ['required', 'email', 'max:255'],
                'adminPassword' => ['required', 'string', 'min:12', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[\W_]/', 'confirmed'],
            ]),
            default => null,
        };
    }
}; ?>

<div class="flex flex-col gap-6">
    @if (! $infrastructurePassed)
        {{-- Infrastructure pre-flight checks failed --}}
        <div class="flex flex-col gap-6">
            <div class="flex w-full flex-col gap-2">
                <h1 class="s-auth-heading">Infrastructure Check</h1>
                <p class="s-auth-description">The following services must be running before setup can begin.</p>
            </div>

            <div class="flex flex-col gap-3">
                @foreach ($infrastructureChecks as $name => $check)
                    <div @class([
                        'flex items-center gap-3 rounded-lg border px-4 py-3',
                        'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950' => $check['passed'],
                        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950' => ! $check['passed'],
                    ])>
                        @if ($check['passed'])
                            <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        @else
                            <flux:icon name="x-circle" class="size-5 text-red-600 dark:text-red-400" />
                        @endif
                        <div>
                            <div class="text-sm font-medium capitalize text-zinc-800 dark:text-zinc-200">{{ $name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $check['message'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <flux:callout variant="danger">
                <flux:callout.heading>Infrastructure not ready</flux:callout.heading>
                <flux:callout.text>Fix the failing checks above, then retry. Run <code>php artisan signals:install</code> if you haven't already.</flux:callout.text>
            </flux:callout>

            <div>
                <flux:button wire:click="retryInfrastructureChecks" variant="primary" size="sm">
                    Retry Checks
                </flux:button>
            </div>
        </div>
    @else
        {{-- Step indicator --}}
        <x-signals.stepper>
            @for ($i = 1; $i <= 6; $i++)
                <div class="s-stepper-stage">
                    @if ($i > 1)
                        <div @class(['s-stepper-line', 'done' => $i <= $currentStep])></div>
                    @endif
                    <button
                        wire:click="goToStep({{ $i }})"
                        @class([
                            's-stepper-circle',
                            'done' => $i < $currentStep,
                            'active' => $i === $currentStep,
                        ])
                        @if($i > $currentStep) disabled @endif
                    >
                        @if ($i < $currentStep)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        @else
                            {{ $i }}
                        @endif
                    </button>
                </div>
            @endfor
        </x-signals.stepper>

        {{-- Step content — keyed to force full DOM replacement between steps --}}
        {{-- (prevents wire:ignore on Flux file inputs from persisting across steps) --}}
        <div wire:key="step-{{ $currentStep }}">
            @if ($currentStep === 1)
                @include('livewire.setup.steps.company')
            @elseif ($currentStep === 2)
                @include('livewire.setup.steps.stores')
            @elseif ($currentStep === 3)
                @include('livewire.setup.steps.profile')
            @elseif ($currentStep === 4)
                @include('livewire.setup.steps.branding')
            @elseif ($currentStep === 5)
                @include('livewire.setup.steps.admin')
            @elseif ($currentStep === 6)
                @include('livewire.setup.steps.review')
            @endif
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div>
                @if ($currentStep > 1)
                    <flux:button wire:click="previousStep" variant="ghost" size="sm">
                        Back
                    </flux:button>
                @endif
            </div>

            <div>
                @if ($currentStep < 6)
                    <flux:button wire:click="nextStep" variant="primary" size="sm">
                        Continue
                    </flux:button>
                @else
                    <flux:button wire:click="completeSetup" variant="primary" size="sm">
                        Complete Setup
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>
