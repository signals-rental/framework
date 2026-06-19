<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\OpportunityCloned;
use App\Verbs\Events\Opportunities\OpportunityCreated;
use Illuminate\Support\Carbon;
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
    use CommitsVerbsEvents;

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
            'subject' => $source->subject,
            'member_id' => $source->member_id,
            'store_id' => $source->store_id,
            'owned_by' => $source->owned_by,
            'venue_id' => $source->venue_id,
            'reference' => $source->reference,
            'description' => $source->description,
            'external_description' => $source->external_description,
            'starts_at' => $this->toIso($source->starts_at),
            'ends_at' => $this->toIso($source->ends_at),
            'currency' => $source->currency_code ?? 'GBP',
            'prices_include_tax' => $source->prices_include_tax,
        ]);
    }

    /**
     * Copy a source line item into an add-item payload. The manual price override
     * (`unit_price`) is passed through as already-minor units; a null defers the
     * clone to the rate engine exactly as the source did.
     */
    private function itemDataFrom(OpportunityItem $item): AddOpportunityItemData
    {
        return AddOpportunityItemData::from([
            'name' => $item->name,
            'item_id' => $item->item_id,
            'item_type' => $item->item_type,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'transaction_type' => $item->transaction_type->value,
            'charge_period' => $item->charge_period->value,
            'starts_at' => $this->toIso($item->starts_at),
            'ends_at' => $this->toIso($item->ends_at),
            'is_optional' => $item->is_optional,
            'discount_percent' => $item->discount_percent,
            'sort_order' => $item->sort_order,
            'notes' => $item->notes,
            'custom_fields' => $item->custom_fields,
            'currency' => $item->currency_code ?? 'GBP',
            // Already-minor units: an int passes straight through the MoneyInput cast.
            'unit_price' => $this->manualUnitPrice($item),
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

    /**
     * The source line's MANUAL price override, if one was set. An item priced by
     * the rate engine carries no manual override, so the clone must NOT pass its
     * resolved unit_price (which would freeze it as a manual override) — it returns
     * null so the clone reprices from the rate engine. A manual override is
     * detected when the stored unit_price diverges from a rate-resolvable line, but
     * since the projection does not persist the override flag, we treat a line with
     * NO product reference (so no rate could ever apply) as carrying a manual price.
     */
    private function manualUnitPrice(OpportunityItem $item): ?int
    {
        // A line with no product reference can never be rate-priced, so its
        // unit_price is inherently manual and must be carried to the clone.
        if ($item->item_id === null) {
            return $item->unit_price !== 0 ? $item->unit_price : null;
        }

        // A product-backed line reprices from the rate engine on the clone; do not
        // pin its resolved price as a manual override.
        return null;
    }

    private function toIso(?\DateTimeInterface $value): ?string
    {
        return $value !== null ? Carbon::parse($value)->toIso8601String() : null;
    }
}
