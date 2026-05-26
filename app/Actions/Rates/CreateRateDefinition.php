<?php

namespace App\Actions\Rates;

use App\Actions\Rates\Concerns\ValidatesRateConfig;
use App\Data\Rates\CreateRateDefinitionData;
use App\Data\Rates\RateDefinitionData;
use App\Events\AuditableEvent;
use App\Models\RateDefinition;
use App\Services\Api\WebhookService;
use App\Services\RateEngine\RateEngineRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateRateDefinition
{
    use ValidatesRateConfig;

    public function __invoke(CreateRateDefinitionData $data): RateDefinitionData
    {
        Gate::authorize('rates.create');

        $strategy = $data->calculation_strategy->value;

        $this->validateRateConfig($strategy, $data->enabled_modifiers, $data->strategy_config, $data->modifier_configs);

        return DB::transaction(function () use ($data, $strategy): RateDefinitionData {
            $registry = app(RateEngineRegistry::class);

            $definition = RateDefinition::create([
                'name' => $data->name,
                'description' => $data->description,
                'calculation_strategy' => $data->calculation_strategy,
                'base_period' => $data->base_period,
                'enabled_modifiers' => $data->enabled_modifiers,
                'strategy_config' => $registry->sanitiseStrategyConfig($strategy, $data->strategy_config),
                'modifier_configs' => $registry->sanitiseModifierConfigs($data->enabled_modifiers, $data->modifier_configs),
                'is_preset' => false,
            ]);

            event(new AuditableEvent($definition, 'rate_definition.created'));

            app(WebhookService::class)->dispatch('rate_definition.created', [
                'rate_definition' => RateDefinitionData::fromModel($definition)->toArray(),
            ]);

            return RateDefinitionData::fromModel($definition);
        });
    }
}
