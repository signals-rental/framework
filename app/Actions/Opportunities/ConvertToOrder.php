<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Enums\ShortagePolicy;
use App\Enums\VersionStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\Rules\ShortageConfirmationRule;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Services\Shortages\ShortageAutoResolver;
use App\Services\Shortages\ShortageConfirmationGate;
use App\Verbs\Events\Opportunities\OpportunityConvertedToOrder;
use App\Verbs\Events\Opportunities\VersionSuperseded;

/**
 * Converts a Quotation into a confirmed Order via the
 * OpportunityConvertedToOrder event.
 *
 * The quote → order transition runs the M7 {@see GuardPipeline} before firing:
 * Permission (opportunities.edit) → Approval (placeholder) → Business Rules →
 * Plugin validators (placeholder). The business-rules stage runs the registered
 * {@see ShortageConfirmationRule}, which applies {@see ShortageAutoResolver} first
 * (when the store has `shortage_auto_resolve_enabled`, §7.5) so auto-executable
 * options (e.g. partial fulfilment) are applied before the
 * {@see ShortageConfirmationGate} evaluates and sees only the RESIDUAL shortage.
 * The gate is a config-driven business rule — store {@see ShortagePolicy} relaxed
 * by the actor's `shortages.ignore` permission — not a hardcoded matrix; a Block
 * decision throws a 422 before any event fires, while Warn records an
 * acknowledgement and proceeds. The pipeline, the version supersession, and the
 * conversion all run inside the same atomic transaction so they commit or roll
 * back together; the Verbs `validate()` invariants (empty-deal / isClosed) remain
 * the final hard-invariant layer inside fire().
 */
class ConvertToOrder
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ?string $shortageNotes = null): OpportunityData
    {
        // §8.8 — when the opportunity carries quote versions, the order is confirmed
        // against ONE version: an Accepted version wins, else the current active
        // version. Resolved BEFORE the gate so shortage detection (which reads the
        // active version's items) reflects the version that will actually ship.
        $confirmedVersionId = $this->resolveConfirmedVersion($opportunity);

        $this->commitVerbs(function () use ($opportunity, $shortageNotes, $confirmedVersionId): void {
            // If a non-active version was confirmed (an Accepted alternative), make
            // it active first so the shortage gate and demand resync read its items.
            if ($confirmedVersionId !== null && $confirmedVersionId !== $opportunity->active_version_id) {
                (new ActivateVersion)(OpportunityVersion::query()->findOrFail($confirmedVersionId));
                $opportunity->refresh();
            }

            // Run the guard pipeline: Permission → Approval → Business Rules
            // (auto-resolve + shortage gate via ShortageConfirmationRule) → Plugin
            // validators. Throws a ValidationException (→ 422) on a shortage Block,
            // or an AuthorizationException (→ 403) without `opportunities.edit`.
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: ShortageConfirmationRule::TRANSITION,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
                notes: $shortageNotes,
            ));

            // Supersede every non-confirmed version (preserving Accepted/Declined
            // history) so only the confirmed version remains live on the order.
            if ($confirmedVersionId !== null) {
                $this->supersedeNonConfirmedVersions($opportunity, $confirmedVersionId);
            }

            OpportunityConvertedToOrder::fire(
                opportunity_id: $opportunity->state_id,
                confirmed_version_id: $confirmedVersionId,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }

    /**
     * Resolve which version the order confirms against (§8.8): the most recently
     * accepted version if any, else the current active version. Null when the
     * opportunity has no versions (conversion behaves exactly as before).
     */
    private function resolveConfirmedVersion(Opportunity $opportunity): ?int
    {
        if ($opportunity->version_count === 0 || $opportunity->active_version_id === 0) {
            return null;
        }

        $accepted = OpportunityVersion::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('status', VersionStatus::Accepted->value)
            ->orderByDesc('accepted_at')
            ->orderByDesc('version_number')
            ->value('id');

        return $accepted ?? $opportunity->active_version_id;
    }

    /**
     * Fire VersionSuperseded for every non-confirmed version on the opportunity.
     */
    private function supersedeNonConfirmedVersions(Opportunity $opportunity, int $confirmedVersionId): void
    {
        $others = OpportunityVersion::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('id', '!=', $confirmedVersionId)
            ->get(['state_id']);

        foreach ($others as $other) {
            VersionSuperseded::fire(
                version_id: $other->state_id,
                superseded_by_version_id: $confirmedVersionId,
            );
        }
    }
}
