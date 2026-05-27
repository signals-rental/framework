<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CalculationStrategy;
use App\Contracts\RateModifier;
use App\Http\Controllers\Api\Controller;
use App\Services\RateEngine\Presets\RatePresets;
use App\Services\RateEngine\RateEngineRegistry;
use App\Support\ConfigSchema\Section;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only metadata about the rate engine — its strategies, modifiers, presets,
 * and composed config schema — so external form builders can render the same
 * rate-definition UI the framework uses.
 */
class RateEngineMetaController extends Controller
{
    public function __construct(private readonly RateEngineRegistry $registry) {}

    /**
     * List the registered calculation strategies.
     */
    #[ApiResponse(200, 'Calculation strategies', type: 'array{strategies: list<array{identifier: string, label: string, allowed_base_periods: list<string>, supports_multiplier: bool, supports_factor: bool}>}')]
    public function strategies(): JsonResponse
    {
        $this->authorizeApi('rates.view', 'rates:read');

        $strategies = array_map(static fn (CalculationStrategy $strategy): array => [
            'identifier' => $strategy->identifier(),
            'label' => $strategy->label(),
            'allowed_base_periods' => array_map(static fn ($period): string => $period->value, $strategy->allowedBasePeriods()),
            'supports_multiplier' => $strategy->supportsMultiplier(),
            'supports_factor' => $strategy->supportsFactor(),
        ], array_values($this->registry->strategies()));

        return response()->json(['strategies' => $strategies]);
    }

    /**
     * List the registered rate modifiers, ordered by priority.
     */
    #[ApiResponse(200, 'Rate modifiers', type: 'array{modifiers: list<array{identifier: string, label: string, priority: int}>}')]
    public function modifiers(): JsonResponse
    {
        $this->authorizeApi('rates.view', 'rates:read');

        $modifiers = array_map(static fn (RateModifier $modifier): array => [
            'identifier' => $modifier->identifier(),
            'label' => $modifier->label(),
            'priority' => $modifier->priority(),
        ], $this->registry->modifiers());

        return response()->json(['modifiers' => $modifiers]);
    }

    /**
     * List the framework-shipped rate definition presets.
     */
    #[ApiResponse(200, 'Rate presets', type: 'array{presets: list<array{slug: string, name: string, description: string, calculation_strategy: string, base_period: string|null, enabled_modifiers: list<string>, strategy_config: array<string, mixed>, modifier_configs: array<string, mixed>}>}')]
    public function presets(): JsonResponse
    {
        $this->authorizeApi('rates.view', 'rates:read');

        $presets = array_map(static fn (array $preset): array => [
            'slug' => $preset['slug'],
            'name' => $preset['name'],
            'description' => $preset['description'],
            'calculation_strategy' => $preset['calculation_strategy']->value,
            'base_period' => $preset['base_period']?->value,
            'enabled_modifiers' => $preset['enabled_modifiers'],
            'strategy_config' => $preset['strategy_config'],
            'modifier_configs' => $preset['modifier_configs'],
        ], RatePresets::all());

        return response()->json(['presets' => $presets]);
    }

    /**
     * Compose the config-form sections for a strategy and its enabled modifiers.
     */
    #[ApiResponse(200, 'Composed config schema', type: 'array{sections: list<array{key: string, label: string, fields: list<array<string, mixed>>}>}')]
    public function schema(Request $request): JsonResponse
    {
        $this->authorizeApi('rates.view', 'rates:read');

        $strategyIds = array_keys($this->registry->strategies());
        $modifierIds = array_map(static fn (RateModifier $modifier): string => $modifier->identifier(), $this->registry->modifiers());

        $validated = $request->validate([
            'strategy' => ['required', 'string', Rule::in($strategyIds)],
            'modifiers' => ['sometimes', 'array'],
            'modifiers.*' => ['string', Rule::in($modifierIds)],
        ]);

        $sections = array_map(
            static fn (Section $section): array => $section->toArray(),
            $this->registry->composeSections($validated['strategy'], $validated['modifiers'] ?? []),
        );

        return response()->json(['sections' => $sections]);
    }
}
