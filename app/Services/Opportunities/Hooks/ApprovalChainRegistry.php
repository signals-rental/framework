<?php

namespace App\Services\Opportunities\Hooks;

/**
 * The approval-chain consumer's declaration point (opportunity-lifecycle.md §12.2
 * "Approval guards"; approval-chain-engine.md) — PLACEHOLDER.
 *
 * The approval-chain engine is not built yet. The guard pipeline's approval STAGE
 * is the {@see App\Guards\Opportunities\Contracts\ApprovalGate} seam (no-op
 * default). This registry is the complementary seam where the future engine
 * DECLARES which transitions require an approval chain, so its
 * {@see ApprovalGate} implementation can look up the chain for a transition.
 *
 * Empty in core (no chains declared) and consulted nowhere in the hot path today
 * — it exists so the approval-chain consumer attaches with ZERO retrofit. Mirrors
 * the WorkflowTriggerRegistry / NotificationRegistry shape.
 */
class ApprovalChainRegistry
{
    /**
     * Registered approval chains keyed by transition. The value is an opaque chain
     * definition the future engine interprets; core never evaluates it.
     *
     * @var array<string, mixed>
     */
    private array $chains = [];

    /**
     * Declare that a transition requires an approval chain (e.g.
     * `opportunity.converted_to_order`).
     */
    public function register(string $transition, mixed $chain): void
    {
        $this->chains[$transition] = $chain;
    }

    public function requiresApproval(string $transition): bool
    {
        return isset($this->chains[$transition]);
    }

    public function forTransition(string $transition): mixed
    {
        return $this->chains[$transition] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->chains;
    }
}
