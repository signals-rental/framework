<?php

use App\Actions\Rates\Concerns\ValidatesRateConfig;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use Illuminate\Validation\ValidationException;

/**
 * Thin harness that exposes the protected compatibility check so the `factor`
 * modifier support branch (ValidatesRateConfig::strategySupportsModifier) can be
 * exercised directly without standing up a full rate-definition write.
 */
function rateConfigValidator(): object
{
    return new class
    {
        use ValidatesRateConfig;

        /**
         * @param  array<int, string>  $modifiers
         */
        public function check(CalculationStrategyType $strategy, array $modifiers, ?BasePeriod $basePeriod = null): void
        {
            $this->validateStrategyCompatibility($strategy, $basePeriod, $modifiers);
        }
    };
}

it('accepts the factor modifier on a strategy that supports it', function () {
    // The Fixed strategy supports the factor modifier — the factor branch resolves
    // to true and no compatibility error is raised (the call returns void cleanly).
    rateConfigValidator()->check(CalculationStrategyType::Fixed, ['factor']);
})->throwsNoExceptions();

it('rejects the factor modifier on a strategy that does not support it', function () {
    // The Hybrid strategy does NOT support the factor modifier — the factor branch
    // resolves to false and a compatibility error is raised. A valid base period is
    // supplied so the ONLY error is the unsupported-modifier one.
    try {
        rateConfigValidator()->check(CalculationStrategyType::Hybrid, ['factor'], BasePeriod::Daily);
        $this->fail('Expected a ValidationException for the unsupported factor modifier.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('enabled_modifiers')
            ->and($e->errors()['enabled_modifiers'][0])->toContain('factor')
            ->and($e->errors())->not->toHaveKey('base_period');
    }
});
