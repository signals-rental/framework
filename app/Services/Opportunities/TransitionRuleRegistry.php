<?php

namespace App\Services\Opportunities;

use App\Guards\Opportunities\Contracts\TransitionRule;
use App\Guards\Opportunities\TransitionContext;

/**
 * Plugin-extensible registry of opportunity transition business rules
 * (opportunity-lifecycle.md §12.2 "Business rule guards").
 *
 * Follows the same shape as {@see App\Services\DemandSourceRegistry} and the other
 * Signals registries: core registers the built-in rules (the shortage
 * confirmation gate, the FX/tax-lock enforcement) in the service provider; plugins
 * register their own through the same interface. The guard pipeline's business-
 * rule stage asks the registry which rules {@see TransitionRule::appliesTo()} the
 * current transition and evaluates them in registration order — so NEW rules and
 * NEW transitions are wired purely through registration, never by editing the
 * pipeline or a named-status switch (Ben's locked steer).
 */
class TransitionRuleRegistry
{
    /** @var array<string, TransitionRule> */
    private array $rules = [];

    public function register(TransitionRule $rule): void
    {
        $this->rules[$rule->key()] = $rule;
    }

    public function has(string $key): bool
    {
        return isset($this->rules[$key]);
    }

    /**
     * @return array<string, TransitionRule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * The rules applicable to the given transition context, in registration
     * order — what the business-rule stage evaluates for that transition.
     *
     * @return list<TransitionRule>
     */
    public function applicableTo(TransitionContext $context): array
    {
        return array_values(array_filter(
            $this->rules,
            static fn (TransitionRule $rule): bool => $rule->appliesTo($context),
        ));
    }
}
