<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Enums\ShortagePolicy;
use App\Models\Opportunity;
use App\Services\Shortages\ShortageAutoResolver;
use App\Services\Shortages\ShortageConfirmationGate;
use App\Verbs\Events\Opportunities\OpportunityConvertedToOrder;
use Illuminate\Support\Facades\Gate;

/**
 * Converts a Quotation into a confirmed Order via the
 * OpportunityConvertedToOrder event.
 *
 * The quote → order transition runs {@see ShortageAutoResolver} first (when the
 * store has `shortage_auto_resolve_enabled`, §7.5) so auto-executable options
 * (e.g. partial fulfilment) are applied before the {@see ShortageConfirmationGate}
 * evaluates and sees only the RESIDUAL shortage. The gate is a config-driven
 * business rule — store {@see ShortagePolicy} relaxed by the actor's
 * `shortages.ignore` permission — not a hardcoded matrix; a Block decision throws
 * a 422 before any event fires, while Warn records an acknowledgement and
 * proceeds. Auto-resolution, the gate, and the conversion all run inside the same
 * atomic transaction so they commit or roll back together.
 */
class ConvertToOrder
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ?string $shortageNotes = null): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($opportunity, $shortageNotes): void {
            // §7.5 — run auto-resolution first (no-op unless the store enables it)
            // so the gate sees only the residual shortage.
            app(ShortageAutoResolver::class)->resolve($opportunity);

            // Business-rule guard: enforce the store's shortage policy before the
            // state transition. Throws a ValidationException (→ 422) on a Block.
            app(ShortageConfirmationGate::class)->enforceForConfirmation($opportunity, $shortageNotes);

            OpportunityConvertedToOrder::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
