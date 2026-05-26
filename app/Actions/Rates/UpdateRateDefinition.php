<?php

namespace App\Actions\Rates;

use App\Actions\Rates\Concerns\ValidatesRateConfig;
use App\Data\Rates\RateDefinitionData;
use App\Data\Rates\UpdateRateDefinitionData;
use App\Events\AuditableEvent;
use App\Models\RateDefinition;
use App\Services\Api\WebhookService;
use App\Services\RateEngine\RateEngineRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateRateDefinition
{
    use ValidatesRateConfig;

    public function __invoke(RateDefinition $definition, UpdateRateDefinitionData $data): RateDefinitionData
    {
        Gate::authorize('rates.edit');

        // Resolve the effective strategy, modifiers and config (incoming values
        // override the persisted ones) so the whole config validates together.
        $strategy = ($data->calculation_strategy ?? $definition->calculation_strategy)->value;
        $enabledModifiers = $data->enabled_modifiers ?? $definition->enabled_modifiers ?? [];
        $strategyConfig = $data->strategy_config ?? $definition->strategy_config ?? [];
        $modifierConfigs = $data->modifier_configs ?? $definition->modifier_configs ?? [];

        $this->validateRateConfig($strategy, $enabledModifiers, $strategyConfig, $modifierConfigs);

        return DB::transaction(function () use ($definition, $data, $strategy, $enabledModifiers, $strategyConfig, $modifierConfigs): RateDefinitionData {
            $registry = app(RateEngineRegistry::class);

            if ($data->name !== null) {
                $definition->name = $data->name;
            }

            if ($data->description !== null) {
                $definition->description = $data->description === '' ? null : $data->description;
            }

            if ($data->calculation_strategy !== null) {
                $definition->calculation_strategy = $data->calculation_strategy;
            }

            if ($data->base_period !== null) {
                $definition->base_period = $data->base_period;
            }

            $definition->enabled_modifiers = $enabledModifiers;
            $definition->strategy_config = $registry->sanitiseStrategyConfig($strategy, $strategyConfig);
            $definition->modifier_configs = $registry->sanitiseModifierConfigs($enabledModifiers, $modifierConfigs);
            $definition->save();

            event(new AuditableEvent($definition, 'rate_definition.updated'));

            app(WebhookService::class)->dispatch('rate_definition.updated', [
                'rate_definition' => RateDefinitionData::fromModel($definition)->toArray(),
            ]);

            return RateDefinitionData::fromModel($definition);
        });
    }
}
