<?php

namespace App\Guards\Opportunities;

use App\Models\Opportunity;

/**
 * The immutable context a transition guard is evaluated against
 * (opportunity-lifecycle.md §12.2 "Configurable Guards").
 *
 * It describes WHICH transition is being attempted and WHAT it would change, in
 * generic, status-name-agnostic terms — so transition rules can be registered and
 * matched by `transition` key and by the `changes` it carries, never by a
 * hardcoded named-status switch (Ben's locked steer). New transitions and new
 * rules compose by adding a key/flag, not by editing the pipeline.
 *
 *  - `transition` — a stable key naming the attempted move, e.g.
 *    `opportunity.convert_to_order`, `opportunity.item.price_override`. Rules
 *    declare the transitions they apply to via {@see TransitionRule::appliesTo()}.
 *  - `opportunity` — the projection the transition acts on (the rule reads its
 *    current state; it must not mutate it).
 *  - `permission` — the gate-layer permission the Permission stage authorises
 *    (the same ability the action already checks), or null to skip that stage.
 *  - `changes` — a free-form, additive map describing the mutation's nature
 *    (e.g. `['changes_rate' => true]`). Rules read flags they care about and
 *    ignore the rest, so the shape grows without breaking existing rules.
 *  - `notes` — optional free-text the actor supplies (e.g. a shortage override
 *    reason), forwarded to side-effecting rules that record an acknowledgement.
 */
final readonly class TransitionContext
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public string $transition,
        public Opportunity $opportunity,
        public ?string $permission = null,
        public array $changes = [],
        public ?string $notes = null,
    ) {}

    /**
     * Read a single change flag, defaulting when the key is absent. Lets a rule
     * ask "does this transition change the rate?" without caring whether the key
     * was set.
     */
    public function changes(string $key, mixed $default = null): mixed
    {
        return $this->changes[$key] ?? $default;
    }

    /**
     * Whether this context targets the given transition key.
     */
    public function isTransition(string $transition): bool
    {
        return $this->transition === $transition;
    }
}
