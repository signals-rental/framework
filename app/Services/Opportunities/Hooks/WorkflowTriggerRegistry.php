<?php

namespace App\Services\Opportunities\Hooks;

/**
 * The workflow consumer's extension point onto the opportunity event stream
 * (opportunity-lifecycle.md §13.2 "Workflows") — PLACEHOLDER.
 *
 * The no-code automation engine (workflows.md) is not built yet. Every committed
 * opportunity Verbs mutation already reaches the Laravel bus as an
 * {@see App\Events\AuditableEvent} (the M2 audit bridge), so the workflow engine
 * can subscribe to that bus to trigger on transitions WITHOUT any change to the
 * event or guard layer — exactly as the webhook bridge
 * ({@see App\Listeners\DispatchWebhookForAuditableEvent}) already does.
 *
 * This registry is the declarative seam for binding a transition key to a workflow
 * trigger so the future engine can register its triggers here at boot. It is empty
 * in core (no triggers registered) and consulted nowhere in the hot path today; it
 * exists so the workflow consumer attaches with ZERO retrofit. Mirrors the
 * NotificationRegistry / TransitionRuleRegistry shape.
 */
class WorkflowTriggerRegistry
{
    /**
     * Registered triggers keyed by transition. The value is an opaque handler
     * reference the future workflow engine interprets (e.g. a workflow id or a
     * callable); core never invokes it.
     *
     * @var array<string, list<mixed>>
     */
    private array $triggers = [];

    /**
     * Bind a workflow trigger to a transition key (e.g.
     * `opportunity.converted_to_order`). Multiple triggers may share a transition.
     */
    public function register(string $transition, mixed $handler): void
    {
        $this->triggers[$transition][] = $handler;
    }

    public function has(string $transition): bool
    {
        return isset($this->triggers[$transition]) && $this->triggers[$transition] !== [];
    }

    /**
     * The handlers bound to a transition, in registration order.
     *
     * @return list<mixed>
     */
    public function forTransition(string $transition): array
    {
        return $this->triggers[$transition] ?? [];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function all(): array
    {
        return $this->triggers;
    }
}
