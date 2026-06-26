<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\ItemPathService;
use App\Verbs\Events\Opportunities\ItemAdded;
use App\Verbs\Events\Opportunities\ItemsRestructured;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Restructures an opportunity's entire line-item tree from an ordered (display
 * pre-order, top-to-bottom) list of `{id, depth}` nodes.
 *
 * The supplied node set must cover the opportunity's whole ACTIVE-version item set
 * exactly once, unless {@see RestructureOpportunityItemsData::$prune_orphans} is
 * enabled (local-first editor sync): then any active item omitted from `nodes` is
 * removed via {@see RemoveOpportunityItem} before paths are rebuilt.
 *
 * Each node's role-based placement is validated against the resolved parent
 * (accessory→product; group/product/service→root or group), then every item's
 * materialised `path` is rebuilt from order + depth and written by firing one
 * event-sourced {@see ItemsRestructured} per item.
 *
 * WHY EVENT-SOURCED: `path` lives on the item event state — {@see ItemAdded::apply()}
 * re-applies the ORIGINAL path on replay, so a plain `update()` would be silently
 * reverted. Routing each path mutation through the stream keeps the new tree
 * replay-stable. The per-item aggregate model means one event targets one item
 * state, so a tree restructure fires N events (mirroring {@see ReorderOpportunityItems}).
 *
 * Placement + completeness validation runs BEFORE entering {@see commitVerbs}, so a
 * rejected tree fires nothing and leaves every path unchanged. The last fired event
 * is the audit anchor (`emit_audit = true`, carrying the full ordered path list), so
 * a restructure records a single `opportunity.items_restructured` row.
 *
 * @return array<int, OpportunityItemData> the items in new (path) order
 */
class RestructureOpportunityItems
{
    use CommitsVerbsEvents;

    /**
     * @return array<int, OpportunityItemData>
     */
    public function __invoke(Opportunity $opportunity, RestructureOpportunityItemsData $data): array
    {
        Gate::authorize('opportunities.edit');

        /** @var Collection<int, OpportunityItem> $items items of the active version, keyed by projection PK */
        $items = $opportunity->items()->get()->keyBy('id');

        $nodeIds = array_map(static fn (array $node): int => (int) $node['id'], $data->nodes);

        if ($data->prune_orphans) {
            $this->removeOrphanItems($opportunity, $nodeIds, $items);
            $items = $opportunity->items()->get()->keyBy('id');
        }

        $this->assertCompleteSet($nodeIds, $items);

        // Resolve each node's own row type for placement validation; the path
        // service does the depth clamping, so client id+depth (+ row item_type) is
        // sufficient and the validated parent matches the rebuilt parent.
        $validateNodes = array_map(function (array $node) use ($items): array {
            /** @var OpportunityItem $item */
            $item = $items->get((int) $node['id']);

            return [
                'id' => (int) $node['id'],
                'depth' => (int) $node['depth'],
                'item_type' => $item->item_type->value,
            ];
        }, $data->nodes);

        $pathService = app(ItemPathService::class);

        try {
            $pathService->validatePlacement($validateNodes);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'nodes' => $e->getMessage(),
            ]);
        }

        $rebuildNodes = array_map(static fn (array $node): array => [
            'id' => (int) $node['id'],
            'depth' => (int) $node['depth'],
        ], $data->nodes);

        $paths = $pathService->rebuild($rebuildNodes);

        // Ordered audit payload: full tree in supplied order, projection PK + new path.
        $orderedPaths = array_map(static fn (int $id): array => [
            'id' => $id,
            'path' => $paths[$id],
        ], $nodeIds);

        $lastIndex = count($nodeIds) - 1;

        $this->commitVerbs(function () use ($items, $nodeIds, $paths, $orderedPaths, $lastIndex): void {
            foreach ($nodeIds as $index => $id) {
                /** @var OpportunityItem $item */
                $item = $items->get($id);

                $isAnchor = $index === $lastIndex;

                ItemsRestructured::fire(
                    opportunity_item_id: $item->state_id,
                    path: $paths[$id],
                    emit_audit: $isAnchor,
                    ordered_paths: $isAnchor ? $orderedPaths : null,
                );
            }
        });

        return $opportunity->fresh(['items'])->items
            ->map(fn (OpportunityItem $item): OpportunityItemData => OpportunityItemData::fromModel($item))
            ->all();
    }

    /**
     * Remove active-version items omitted from the authoritative local node list.
     *
     * @param  list<int>  $nodeIds
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function removeOrphanItems(Opportunity $opportunity, array $nodeIds, Collection $items): void
    {
        $nodeIdSet = collect($nodeIds);
        $orphanIds = $items->keys()->diff($nodeIdSet)->values()->all();

        if ($orphanIds === []) {
            return;
        }

        foreach ($orphanIds as $orphanId) {
            /** @var OpportunityItem|null $item */
            $item = $items->get($orphanId);

            if ($item === null) {
                continue;
            }

            (new RemoveOpportunityItem)($item);
        }

        $opportunity->refresh();
    }

    /**
     * Assert the supplied node ids cover the opportunity's active-version item set
     * exactly once: no duplicates, no foreign ids, no omissions.
     *
     * @param  list<int>  $nodeIds
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function assertCompleteSet(array $nodeIds, Collection $items): void
    {
        if (count($nodeIds) !== count(array_unique($nodeIds))) {
            throw ValidationException::withMessages([
                'nodes' => 'The tree must include every line item exactly once.',
            ]);
        }

        foreach ($nodeIds as $id) {
            if (! $items->has($id)) {
                throw ValidationException::withMessages([
                    'nodes' => 'One or more line items do not belong to this opportunity.',
                ]);
            }
        }

        if (count($nodeIds) !== $items->count()) {
            throw ValidationException::withMessages([
                'nodes' => 'The tree must include every line item exactly once.',
            ]);
        }
    }
}
