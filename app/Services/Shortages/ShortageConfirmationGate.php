<?php

namespace App\Services\Shortages;

use App\Enums\ShortagePolicy;
use App\Events\AuditableEvent;
use App\Models\Opportunity;
use App\Models\ShortageAcknowledgement;
use App\ValueObjects\ConfirmationGateResult;
use App\ValueObjects\ShortageCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The shortage confirmation gate (shortage-resolution-sub-hires.md §7).
 *
 * The first concrete config-driven transition business rule — a precursor to the
 * M7 guard pipeline. It is deliberately NOT a hardcoded matrix: the decision is
 * the store's {@see ShortagePolicy}, relaxed by the actor's `shortages.ignore`
 * permission (the spec's `can_ignore_shortages`), applied to the shortages the
 * {@see ShortageDetector} computes. New transitions (the M5 dispatch gate) reuse
 * {@see evaluate()} / {@see resolvePolicy()} rather than re-implementing the
 * Block/Warn/Allow logic.
 *
 * It is invoked as a business-rule guard inside an action (e.g. ConvertToOrder),
 * not as a pure Verbs state guard, because it consults store config + actor
 * permissions and has a side effect (recording an acknowledgement) — concerns the
 * event's `validate()` must stay free of.
 */
class ShortageConfirmationGate
{
    /** The permission that relaxes the gate (spec: `can_ignore_shortages`). */
    public const string IGNORE_PERMISSION = 'shortages.ignore';

    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageEventRecorder $events,
    ) {}

    /**
     * Evaluate the gate for an opportunity without side effects — detect
     * shortages, resolve the effective policy, and return the decision.
     */
    public function evaluate(Opportunity $opportunity): ConfirmationGateResult
    {
        $shortages = $this->detector->forOpportunity($opportunity)->unresolved();

        $storePolicy = $opportunity->store?->shortagePolicy() ?? ShortagePolicy::default();
        $effective = $this->resolvePolicy($storePolicy);

        return new ConfirmationGateResult(
            decision: $effective,
            shortages: $shortages,
            storePolicy: $storePolicy,
            permissionUsed: $effective !== $storePolicy,
        );
    }

    /**
     * Enforce the gate for a quote → order confirmation. Detects shortages, and:
     *
     *  - Block (no override): throws a 422 listing the shortages.
     *  - Block relaxed to Warn by permission, or a Warn policy: records an
     *    acknowledgement and proceeds.
     *  - Allow: proceeds silently (shortages remain visible/computed elsewhere).
     *
     * @throws ValidationException when the gate blocks the transition
     */
    public function enforceForConfirmation(Opportunity $opportunity, ?string $notes = null): ConfirmationGateResult
    {
        $result = $this->evaluate($opportunity);

        if ($result->blocks()) {
            throw ValidationException::withMessages([
                'shortages' => [$this->blockMessage($result->shortages)],
            ]);
        }

        // Emit shortage.detected telemetry only when the gate does NOT block. The
        // caller runs this inside its DB::transaction (commitVerbs); emitting before
        // the throw would write the telemetry rows then roll them straight back.
        if ($result->shortages->isNotEmpty()) {
            $this->events->detected($result->shortages);
        }

        if ($result->acknowledgementRequired()) {
            $this->recordAcknowledgement($opportunity, $result, $notes);
        }

        return $result;
    }

    /**
     * Resolve the effective policy for the current actor.
     *
     * When the actor holds {@see self::IGNORE_PERMISSION} (`shortages.ignore`), the
     * store policy is relaxed one level (Block→Warn, Warn→Allow) via
     * {@see ShortagePolicy::relaxedByPermission()}.
     *
     * **Owner implicit override (intentional).** Application owners receive every
     * permission through the {@see Gate::before()} all-access hook in
     * {@see AppServiceProvider}, so `Gate::allows(self::IGNORE_PERMISSION)` is
     * always true for owners even when they were never explicitly granted
     * `shortages.ignore`. Owners therefore always receive the relaxed policy on a
     * Block store — conversion proceeds with an acknowledgement recorded, not a
     * hard block. This is deliberate: owners are the ultimate operational override.
     *
     * Making Block absolute for all actors (including owners) would require a
     * separate setting (e.g. `shortage_block_is_absolute`) that is **not**
     * currently implemented; do not assume Block is non-overridable without that
     * flag.
     */
    public function resolvePolicy(ShortagePolicy $storePolicy): ShortagePolicy
    {
        if (Gate::allows(self::IGNORE_PERMISSION)) {
            return $storePolicy->relaxedByPermission();
        }

        return $storePolicy;
    }

    /**
     * Record a confirmation-gate acknowledgement with a frozen shortage snapshot
     * (§7.3).
     */
    public function recordAcknowledgement(
        Opportunity $opportunity,
        ConfirmationGateResult $result,
        ?string $notes = null,
    ): ShortageAcknowledgement {
        $acknowledgement = ShortageAcknowledgement::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => auth()->id(),
            'acknowledged_at' => now(),
            'policy_at_time' => $result->storePolicy->value,
            'permission_used' => $result->permissionUsed,
            'shortages_snapshot' => $result->shortages->toSnapshots(),
            'notes' => $notes,
        ]);

        event(new AuditableEvent(
            model: $opportunity,
            action: 'shortage.acknowledged',
            newValues: [
                'policy_at_time' => $result->storePolicy->value,
                'permission_used' => $result->permissionUsed,
                'shortage_count' => $result->shortages->count(),
            ],
        ));

        return $acknowledgement;
    }

    private function blockMessage(ShortageCollection $shortages): string
    {
        $count = $shortages->count();

        return "This order has {$count} unresolved shortage(s); resolve them or obtain the permission to proceed before converting to an order.";
    }
}
