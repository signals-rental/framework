<?php

namespace Database\Seeders;

use App\Models\RateDefinition;
use App\Services\RateEngine\Presets\RatePresets;
use Illuminate\Database\Seeder;

class RateDefinitionPresetSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RatePresets::all() as $preset) {
            RateDefinition::query()->updateOrCreate(
                ['preset_slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'calculation_strategy' => $preset['calculation_strategy'],
                    'base_period' => $preset['base_period'],
                    'enabled_modifiers' => $preset['enabled_modifiers'],
                    'strategy_config' => $preset['strategy_config'],
                    'modifier_configs' => $preset['modifier_configs'],
                    'is_preset' => true,
                ],
            );
        }
    }
}
