<?php

use App\Actions\Rates\CreateRateDefinition;
use App\Actions\Rates\UpdateRateDefinition;
use App\Data\Rates\CreateRateDefinitionData;
use App\Data\Rates\UpdateRateDefinitionData;
use App\Models\RateDefinition;
use App\Services\RateEngine\Presets\RatePresets;
use App\Services\RateEngine\RateEngineRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Rate Definition')] class extends Component {
    public ?int $definitionId = null;

    public bool $presetChosen = false;

    public string $name = '';

    public string $description = '';

    public string $calculationStrategy = 'period';

    public ?string $basePeriod = null;

    /** @var list<string> */
    public array $enabledModifiers = [];

    /** @var array<string, mixed> */
    public array $strategyConfig = [];

    /** @var array<string, array<string, mixed>> */
    public array $modifierConfigs = [];

    public function mount(?RateDefinition $rateDefinition = null): void
    {
        if ($rateDefinition?->exists) {
            $this->definitionId = $rateDefinition->id;
            $this->presetChosen = true;
            $this->name = $rateDefinition->name;
            $this->description = $rateDefinition->description ?? '';
            $this->calculationStrategy = $rateDefinition->calculation_strategy->value;
            $this->basePeriod = $rateDefinition->base_period?->value;
            $this->enabledModifiers = $rateDefinition->enabled_modifiers ?? [];
            $this->strategyConfig = $rateDefinition->strategy_config ?? [];
            $this->modifierConfigs = $rateDefinition->modifier_configs ?? [];
        }
    }

    public function choosePreset(string $slug): void
    {
        $preset = collect(RatePresets::all())->firstWhere('slug', $slug);

        if ($preset === null) {
            return;
        }

        $this->name = $preset['name'];
        $this->description = $preset['description'];
        $this->calculationStrategy = $preset['calculation_strategy']->value;
        $this->basePeriod = $preset['base_period']?->value;
        $this->enabledModifiers = $preset['enabled_modifiers'];
        $this->strategyConfig = $this->registry()->strategy($this->calculationStrategy)->configSchema()->defaults();
        $this->modifierConfigs = [];

        foreach ($this->enabledModifiers as $id) {
            $this->modifierConfigs[$id] = $this->registry()->modifier($id)->configSchema()->defaults();
        }

        $this->presetChosen = true;
    }

    public function fromScratch(): void
    {
        $this->calculationStrategy = 'period';
        $this->applyStrategyDefaults();
        $this->presetChosen = true;
    }

    public function updatedCalculationStrategy(): void
    {
        $this->enabledModifiers = array_values(array_filter(
            $this->enabledModifiers,
            fn (string $id): bool => $this->strategySupportsModifier($id),
        ));

        $this->applyStrategyDefaults(keepModifiers: true);
    }

    public function updatedEnabledModifiers(): void
    {
        $this->enabledModifiers = array_values(array_filter(
            $this->enabledModifiers,
            fn (string $id): bool => $this->strategySupportsModifier($id),
        ));

        foreach (array_keys($this->modifierConfigs) as $id) {
            if (! in_array($id, $this->enabledModifiers, true)) {
                unset($this->modifierConfigs[$id]);
            }
        }

        foreach ($this->enabledModifiers as $id) {
            if (! isset($this->modifierConfigs[$id])) {
                $this->modifierConfigs[$id] = $this->registry()->modifier($id)->configSchema()->defaults();
            }
        }
    }

    public function addRow(string $path): void
    {
        [$property, $rest] = $this->splitPath($path);
        $state = $this->stateFor($property);
        $rows = Arr::get($state, $rest, []);
        $rows = is_array($rows) ? $rows : [];
        $rows[] = [];
        Arr::set($state, $rest, array_values($rows));
        $this->putState($property, $state);
    }

    public function removeRow(string $path, int $index): void
    {
        [$property, $rest] = $this->splitPath($path);
        $state = $this->stateFor($property);
        $rows = array_values(Arr::get($state, $rest, []));
        unset($rows[$index]);
        Arr::set($state, $rest, array_values($rows));
        $this->putState($property, $state);
    }

    public function moveRow(string $path, int $index, int $direction): void
    {
        [$property, $rest] = $this->splitPath($path);
        $state = $this->stateFor($property);
        $rows = array_values(Arr::get($state, $rest, []));
        $target = $index + $direction;

        if ($target < 0 || $target >= count($rows)) {
            return;
        }

        [$rows[$index], $rows[$target]] = [$rows[$target], $rows[$index]];
        Arr::set($state, $rest, $rows);
        $this->putState($property, $state);
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'calculationStrategy' => ['required', 'string'],
        ]);

        $payload = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'calculation_strategy' => $this->calculationStrategy,
            'base_period' => $this->basePeriod,
            'enabled_modifiers' => $this->enabledModifiers,
            'strategy_config' => $this->strategyConfig,
            'modifier_configs' => $this->modifierConfigs,
        ];

        try {
            if ($this->definitionId !== null) {
                $definition = RateDefinition::findOrFail($this->definitionId);
                (new UpdateRateDefinition)($definition, UpdateRateDefinitionData::from($payload));
            } else {
                (new CreateRateDefinition)(CreateRateDefinitionData::from($payload));
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($this->mapConfigErrorKey($key), $message);
                }
            }

            return;
        }

        $this->redirect(route('admin.settings.rate-definitions'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = $this->registry();
        $strategy = $registry->strategy($this->calculationStrategy);

        return [
            'isEditing' => $this->definitionId !== null,
            'presets' => RatePresets::all(),
            'strategies' => $registry->strategies(),
            'basePeriodOptions' => $strategy->allowedBasePeriods(),
            'availableModifiers' => array_values(array_filter(
                $registry->modifiers(),
                fn ($modifier): bool => $this->strategySupportsModifier($modifier->identifier()),
            )),
            'sections' => $this->presetChosen
                ? $registry->composeSections($this->calculationStrategy, $this->enabledModifiers)
                : [],
        ];
    }

    private function registry(): RateEngineRegistry
    {
        return app(RateEngineRegistry::class);
    }

    private function strategySupportsModifier(string $id): bool
    {
        $strategy = $this->registry()->strategy($this->calculationStrategy);

        return match ($id) {
            'multiplier' => $strategy->supportsMultiplier(),
            'factor' => $strategy->supportsFactor(),
            default => false,
        };
    }

    private function applyStrategyDefaults(bool $keepModifiers = false): void
    {
        $strategy = $this->registry()->strategy($this->calculationStrategy);
        $allowed = array_map(fn ($period): string => $period->value, $strategy->allowedBasePeriods());

        if (! in_array($this->basePeriod, $allowed, true)) {
            $this->basePeriod = $allowed[0] ?? null;
        }

        $this->strategyConfig = $strategy->configSchema()->defaults();

        if (! $keepModifiers) {
            $this->enabledModifiers = [];
        }

        $configs = [];
        foreach ($this->enabledModifiers as $id) {
            $configs[$id] = $this->modifierConfigs[$id] ?? $this->registry()->modifier($id)->configSchema()->defaults();
        }
        $this->modifierConfigs = $configs;
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFor(string $property): array
    {
        return $property === 'strategyConfig' ? $this->strategyConfig : $this->modifierConfigs;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function putState(string $property, array $state): void
    {
        if ($property === 'strategyConfig') {
            $this->strategyConfig = $state;
        } else {
            $this->modifierConfigs = $state;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPath(string $path): array
    {
        $property = strtok($path, '.') ?: $path;

        return [$property, substr($path, strlen($property) + 1)];
    }

    private function mapConfigErrorKey(string $key): string
    {
        if (str_starts_with($key, 'strategy_config')) {
            return 'strategyConfig'.substr($key, strlen('strategy_config'));
        }

        if (str_starts_with($key, 'modifier_configs')) {
            return 'modifierConfigs'.substr($key, strlen('modifier_configs'));
        }

        return $key;
    }
}; ?>

<section class="w-full">
    <x-admin.layout
        group="pricing"
        :title="$isEditing ? 'Edit Rate Definition' : 'New Rate Definition'"
        description="Configure a composable pricing engine: a calculation strategy, base period, and optional modifiers."
    >
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Rate Definitions', 'href' => route('admin.settings.rate-definitions')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate>Back</flux:button>
        </x-slot:actions>

        @if(! $presetChosen)
            <x-signals.form-section title="Choose a starting point" description="Start from an industry-standard preset, or build your own.">
                <div class="s-admin-landing-grid">
                    @foreach($presets as $preset)
                        <button type="button" wire:key="preset-{{ $preset['slug'] }}" wire:click="choosePreset('{{ $preset['slug'] }}')" class="s-module-card enabled text-left">
                            <div class="s-module-icon"><flux:icon.calculator class="!size-5" /></div>
                            <div class="s-module-info">
                                <div class="s-module-name">{{ $preset['name'] }}</div>
                                <div class="s-module-desc">{{ $preset['description'] }}</div>
                            </div>
                        </button>
                    @endforeach
                    <button type="button" wire:click="fromScratch" class="s-module-card enabled text-left">
                        <div class="s-module-icon"><flux:icon.plus class="!size-5" /></div>
                        <div class="s-module-info">
                            <div class="s-module-name">From Scratch</div>
                            <div class="s-module-desc">Build a custom rate definition from the ground up.</div>
                        </div>
                    </button>
                </div>
            </x-signals.form-section>
        @else
            <form wire:submit="save" class="space-y-8">
                <x-signals.form-section title="Details">
                    <div class="space-y-4">
                        <flux:input wire:model="name" label="Name" required />
                        <flux:textarea wire:model="description" label="Description" rows="2" />
                    </div>
                </x-signals.form-section>

                <x-signals.form-section title="Engine" description="The calculation strategy, base period, and which modifiers apply.">
                    <div class="space-y-4">
                        <flux:select wire:model.live="calculationStrategy" label="Calculation Strategy">
                            @foreach($strategies as $strategy)
                                <flux:select.option value="{{ $strategy->identifier() }}">{{ $strategy->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if(! empty($basePeriodOptions))
                            <flux:select wire:model="basePeriod" label="Base Period">
                                @foreach($basePeriodOptions as $period)
                                    <flux:select.option value="{{ $period->value }}">{{ $period->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if(! empty($availableModifiers))
                            <div class="s-field">
                                <label class="s-field-label">Modifiers</label>
                                <p class="s-field-help">Layer tiered duration multipliers and quantity factors on top of the base rate.</p>
                                <div class="mt-2 flex flex-col gap-2">
                                    @foreach($availableModifiers as $modifier)
                                        <flux:checkbox
                                            wire:key="modifier-{{ $modifier->identifier() }}"
                                            wire:model.live="enabledModifiers"
                                            value="{{ $modifier->identifier() }}"
                                            :label="$modifier->label()"
                                        />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </x-signals.form-section>

                @foreach($sections as $section)
                    @php
                        $isOptions = $section->key === 'options';
                        $basePath = $isOptions ? 'strategyConfig' : 'modifierConfigs.'.$section->key;
                        $scopeValues = $isOptions ? $strategyConfig : ($modifierConfigs[$section->key] ?? []);
                    @endphp
                    <x-signals.form-section :title="$section->label" wire:key="section-{{ $section->key }}">
                        <div class="space-y-4">
                            @foreach($section->schema->fields() as $field)
                                <x-config-schema.field :field="$field->toArray()" :path="$basePath" :values="$scopeValues" />
                            @endforeach
                        </div>
                    </x-signals.form-section>
                @endforeach

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Rate Definition' }}</flux:button>
                    <flux:button variant="ghost" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate>Cancel</flux:button>
                </div>
            </form>
        @endif
    </x-admin.layout>
</section>
