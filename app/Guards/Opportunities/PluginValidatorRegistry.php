<?php

namespace App\Guards\Opportunities;

use App\Guards\Opportunities\Contracts\TransitionRule;

/**
 * The plugin-validator stage's extension point (opportunity-lifecycle.md §12.2
 * "Plugin guards" + §16.2) — PLACEHOLDER.
 *
 * Mirrors the {@see App\Services\Opportunities\TransitionRuleRegistry} shape so a
 * plugin registers a {@see TransitionRule} validator through the Plugin SDK that
 * runs in its OWN pipeline stage (after core business rules), with no retrofit to
 * the pipeline. It is empty in core today — no core validators are registered —
 * but the {@see App\Guards\Opportunities\Stages\PluginValidatorStage} already
 * consults it, so plugins attach with zero pipeline change.
 *
 * Kept separate from the business-rule registry so a plugin validator can never
 * be confused with (or accidentally relax) a core business rule.
 */
class PluginValidatorRegistry
{
    /** @var array<string, TransitionRule> */
    private array $validators = [];

    public function register(TransitionRule $validator): void
    {
        $this->validators[$validator->key()] = $validator;
    }

    public function has(string $key): bool
    {
        return isset($this->validators[$key]);
    }

    /**
     * @return array<string, TransitionRule>
     */
    public function all(): array
    {
        return $this->validators;
    }

    /**
     * The validators applicable to the given transition, in registration order.
     *
     * @return list<TransitionRule>
     */
    public function applicableTo(TransitionContext $context): array
    {
        return array_values(array_filter(
            $this->validators,
            static fn (TransitionRule $validator): bool => $validator->appliesTo($context),
        ));
    }
}
