<?php

use App\Models\RateDefinition;
use App\Services\RateEngine\Presets\RatePresets;
use Database\Seeders\RateDefinitionPresetSeeder;

it('seeds one preset rate definition per preset', function () {
    $this->seed(RateDefinitionPresetSeeder::class);

    expect(RateDefinition::query()->presets()->count())->toBe(11)
        ->and(RateDefinition::query()->count())->toBe(11);
});

it('flags every seeded definition as a preset with its slug', function () {
    $this->seed(RateDefinitionPresetSeeder::class);

    foreach (RatePresets::all() as $preset) {
        $definition = RateDefinition::query()->where('preset_slug', $preset['slug'])->first();

        expect($definition)->not->toBeNull()
            ->and($definition->is_preset)->toBeTrue()
            ->and($definition->name)->toBe($preset['name'])
            ->and($definition->calculation_strategy)->toBe($preset['calculation_strategy'])
            ->and($definition->base_period)->toBe($preset['base_period'])
            ->and($definition->enabled_modifiers)->toBe($preset['enabled_modifiers']);
    }
});

it('is idempotent across repeated runs', function () {
    $this->seed(RateDefinitionPresetSeeder::class);
    $this->seed(RateDefinitionPresetSeeder::class);

    expect(RateDefinition::query()->count())->toBe(11);
});

it('updates a preset in place when its definition changes', function () {
    $this->seed(RateDefinitionPresetSeeder::class);

    RateDefinition::query()->where('preset_slug', 'daily-rate')->update(['name' => 'Tampered']);

    $this->seed(RateDefinitionPresetSeeder::class);

    expect(RateDefinition::query()->where('preset_slug', 'daily-rate')->value('name'))->toBe('Daily Rate')
        ->and(RateDefinition::query()->count())->toBe(11);
});

it('does not seed the cut Days Used Rate preset (maps to Daily Rate on RMS import)', function () {
    $this->seed(RateDefinitionPresetSeeder::class);

    expect(RateDefinition::query()->where('preset_slug', 'days-used-rate')->exists())->toBeFalse()
        ->and(RateDefinition::query()->where('preset_slug', 'daily-rate')->exists())->toBeTrue();
});
