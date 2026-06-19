<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\OpportunityVersionData;
use App\Enums\VersionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\VersionCreated;
use App\Verbs\Events\Opportunities\VersionSuperseded;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

/**
 * Creates a quote version on an opportunity (opportunity-lifecycle.md §8.6).
 *
 * Mirrors {@see CloneOpportunity}: it fires the genesis {@see VersionCreated}
 * (which projects the version row and promotes it to active on the opportunity),
 * then clones the SOURCE version's line items into the new version's scope by
 * firing the standard {@see ItemAdded} flow via {@see AddOpportunityItem} — so the
 * clone's demands and totals rebuild naturally from the same pricing pipeline and
 * the whole tree is replay-stable. A REVISION supersedes its parent via
 * {@see VersionSuperseded}; alternatives coexist.
 *
 * The new version's id and (replay-stable) version_number are allocated at
 * fire-time and baked into the event payload. The whole operation — version row,
 * every cloned item, the supersede — runs inside one atomic commitVerbs boundary.
 */
class CreateVersion
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, CreateVersionData $data): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $source = $this->resolveSourceVersion($opportunity, $data);

        $versionType = $data->version_type;
        $createdBy = Auth::id();

        $newVersionId = $this->commitVerbs(function () use ($opportunity, $source, $data, $versionType, $createdBy): int {
            $versionId = app(SequenceAllocator::class)->next('opportunity_versions');

            // Replay-stable version_number: the running max issued + 1, derived from
            // the opportunity's projected version_count (set by every VersionCreated)
            // and baked into the event — never a MAX() query in apply().
            $versionNumber = $opportunity->version_count + 1;

            $parentVersionId = $versionType === VersionType::Revision->value
                ? ($source?->id)
                : null;

            VersionCreated::fire(
                version_id: $versionId,
                opportunity_pk: $opportunity->id,
                opportunity_id: $opportunity->state_id,
                version_number: $versionNumber,
                version_type: $versionType,
                parent_version_id: $parentVersionId,
                label: $data->label instanceof Optional ? null : $data->label,
                created_by: $createdBy,
                notes: $data->notes instanceof Optional ? null : $data->notes,
                // Snapshot the parent opportunity's currency context onto the version
                // so its totals are self-describing. Baked into the event payload, so
                // replay reproduces the same snapshot even if the parent later changes.
                currency_code: $opportunity->currency_code,
                exchange_rate: $opportunity->exchange_rate,
            );

            // Clone the source version's items into the new version scope.
            if ($source !== null) {
                foreach ($source->items()->get() as $item) {
                    (new AddOpportunityItem)($opportunity, $this->itemDataFrom($item, $versionId));
                }
            }

            // A revision supersedes the version it iterates on; alternatives coexist.
            if ($parentVersionId !== null) {
                $parentStateId = OpportunityVersion::query()->whereKey($parentVersionId)->value('state_id');

                if ($parentStateId !== null) {
                    VersionSuperseded::fire(
                        version_id: $parentStateId,
                        superseded_by_version_id: $versionId,
                    );
                }
            }

            return $versionId;
        });

        return OpportunityVersionData::fromModel(
            OpportunityVersion::query()->whereKey($newVersionId)->with('items')->firstOrFail(),
        );
    }

    /**
     * Resolve the version whose items seed the new version: an explicit
     * `source_version_id` (validated to belong to the opportunity), else the
     * opportunity's current active version, else none (first version on a
     * versionless opportunity — its existing items are migrated below).
     */
    private function resolveSourceVersion(Opportunity $opportunity, CreateVersionData $data): ?OpportunityVersion
    {
        $sourceId = $data->source_version_id instanceof Optional ? null : $data->source_version_id;

        if ($sourceId !== null) {
            $source = OpportunityVersion::query()
                ->whereKey($sourceId)
                ->where('opportunity_id', $opportunity->id)
                ->first();

            if ($source === null) {
                throw ValidationException::withMessages([
                    'source_version_id' => ['The source version does not belong to this opportunity.'],
                ]);
            }

            return $source;
        }

        if ($opportunity->active_version_id > 0) {
            return OpportunityVersion::query()->whereKey($opportunity->active_version_id)->first();
        }

        return null;
    }

    /**
     * Copy a source line item into an add-item payload scoped to the new version.
     * Mirrors {@see CloneOpportunity::itemDataFrom} — a product-backed line
     * reprices from the rate engine on the clone; a manual/no-product line carries
     * its manual price through.
     */
    private function itemDataFrom(OpportunityItem $item, int $versionId): AddOpportunityItemData
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
            'unit_price' => $this->manualUnitPrice($item),
            'version_id' => $versionId,
        ]);
    }

    /**
     * The source line's MANUAL price override, if any. A product-backed line
     * reprices from the rate engine (null); a no-product line is inherently manual.
     */
    private function manualUnitPrice(OpportunityItem $item): ?int
    {
        if ($item->item_id === null) {
            return $item->unit_price !== 0 ? $item->unit_price : null;
        }

        return null;
    }

    private function toIso(?\DateTimeInterface $value): ?string
    {
        return $value !== null ? Carbon::parse($value)->toIso8601String() : null;
    }
}
