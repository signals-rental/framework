<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\ClonesOpportunityItems;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Verbs\Events\Opportunities\OpportunityCloned;
use App\Verbs\Events\Opportunities\OpportunityCreated;
use Illuminate\Support\Facades\Gate;
use Thunk\Verbs\Facades\Verbs;

/**
 * Clones an opportunity into a NEW Draft quotation.
 *
 * The clean event-sourced approach: a fresh {@see OpportunityCreated}
 * is fired for the new opportunity (always landing as a Draft with a freshly
 * allocated number — state/status are NEVER copied), then the source's line items
 * and costs are replayed through the existing {@see AddOpportunityItem} /
 * {@see AddOpportunityCost} flows. Routing through those genesis events means the
 * clone's demands and totals rebuild naturally from the same pricing/tax pipeline,
 * and the whole tree is replay-stable. A final {@see OpportunityCloned} audit event
 * stamps the lineage (source → new id).
 *
 * The entire clone — new header, every item, every cost, the lineage event — runs
 * inside one atomic {@see CommitsVerbsEvents::commitVerbs()} boundary, so a partial
 * clone can never be left behind: any failure rolls back the new opportunity and
 * all its children together.
 */
class CloneOpportunity
{
    use ClonesOpportunityItems, CommitsVerbsEvents;

    public function __invoke(Opportunity $source): OpportunityData
    {
        Gate::authorize('opportunities.create');

        $source->loadMissing(['items', 'costs']);

        $newId = $this->commitVerbs(function () use ($source): int {
            $created = (new CreateOpportunity)($this->headerDataFrom($source));

            $clone = Opportunity::query()->findOrFail($created->id);

            foreach ($source->items as $item) {
                (new AddOpportunityItem)($clone, $this->itemDataFrom($item));
            }

            foreach ($source->costs as $cost) {
                (new AddOpportunityCost)($clone, $this->costDataFrom($cost));
            }

            OpportunityCloned::fire(
                // The StateId is the clone's snowflake state_id (as the other
                // transition events take it), not the small projection PK.
                opportunity_id: $clone->state_id,
                source_opportunity_id: $source->id,
            );
            Verbs::commit();

            return $clone->id;
        });

        return OpportunityData::fromModel(
            Opportunity::query()->whereKey($newId)->with(['items', 'costs'])->firstOrFail(),
        );
    }

    /**
     * Build the new-Draft header payload from the source header. The number is NOT
     * copied (CreateOpportunity allocates a fresh one); the document currency and
     * tax-inclusive flag are preserved so the cloned lines reprice identically.
     */
    private function headerDataFrom(Opportunity $source): CreateOpportunityData
    {
        return CreateOpportunityData::from([
            // Distinguish the clone in lists/headers; cap the base so the appended
            // suffix can never breach the subject's 255-char limit.
            'subject' => mb_substr($source->subject, 0, 246).' (cloned)',
            'member_id' => $source->member_id,
            'store_id' => $source->store_id,
            'owned_by' => $source->owned_by,
            'venue_id' => $source->venue_id,
            'reference' => $source->reference,
            'description' => $source->description,
            'external_description' => $source->external_description,
            'starts_at' => $this->toIso($source->starts_at),
            'ends_at' => $this->toIso($source->ends_at),
            'charge_starts_at' => $this->toIso($source->charge_starts_at),
            'charge_ends_at' => $this->toIso($source->charge_ends_at),
            'currency' => $source->currency_code ?? 'GBP',
            'prices_include_tax' => $source->prices_include_tax,
            'tag_list' => $source->tag_list ?? [],
        ]);
    }

    /**
     * Copy a source cost into an add-cost payload. `amount` is the per-unit charge
     * in already-minor units (passed straight through the MoneyInput cast).
     */
    private function costDataFrom(OpportunityCost $cost): AddOpportunityCostData
    {
        return AddOpportunityCostData::from([
            'description' => $cost->description,
            'cost_type' => $cost->cost_type->value,
            'transaction_type' => $cost->transaction_type->value,
            'quantity' => (string) $cost->quantity,
            'is_optional' => $cost->is_optional,
            'sort_order' => $cost->sort_order,
            'notes' => $cost->notes,
            'currency' => $cost->currency_code ?? 'GBP',
            'amount' => $cost->amount,
        ]);
    }
}
