<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityItemType;
use App\Services\Prototypes\PrototypeEditorService;
use InvalidArgumentException;

/**
 * Pure, dependency-free "tree geometry" service for the unified, Current-RMS
 * style opportunity line-item tree.
 *
 * It owns two deterministic operations, both driven solely from an ordered
 * (display pre-order, top-to-bottom) list of nodes:
 *
 *  - {@see rebuild()} recomputes every node's materialized `path` (a
 *    zero-padded segment per tree level) from order + depth.
 *  - {@see validatePlacement()} asserts the role-based legality of each node's
 *    resolved parent.
 *
 * This is the REPLAY-CRITICAL core shared by the restructure action and its
 * event, so it performs NO database or model access and is fully deterministic:
 * identical input always yields identical output. The algorithm is ported from
 * {@see PrototypeEditorService::persistTree()} (its
 * counter/prefix/clamp logic) with all DB writes removed.
 */
class ItemPathService
{
    /**
     * Width (in characters) of one zero-padded path segment per tree level.
     */
    private const SEGMENT_WIDTH = 4;

    /**
     * Rebuild every node's materialized path from an ordered (display pre-order,
     * top-to-bottom) list of `[id, depth]`. Depth is 1-based.
     *
     * Depth is CLAMPED so it can never exceed `previousDepth + 1` (a child is at
     * most one level deeper than the row above it) and is never below 1. Each
     * node's path is its parent's full path prefix concatenated with a
     * zero-padded per-depth counter; counters deeper than the current depth are
     * reset whenever depth decreases, so siblings under a new parent restart at
     * one.
     *
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array<int, string> id => path
     */
    public function rebuild(array $nodes): array
    {
        /** @var array<int, int> $counters per-depth counter; index == depth */
        $counters = [];
        /** @var array<int, string> $prefixAtDepth running path produced at each depth */
        $prefixAtDepth = [];
        $previousDepth = 0;

        /** @var array<int, string> $paths */
        $paths = [];

        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $depth = $this->clampDepth((int) $node['depth'], $previousDepth);

            $this->resetDeeperThan($counters, $depth);

            $counters[$depth] = ($counters[$depth] ?? 0) + 1;

            $parentPrefix = $depth > 1 ? ($prefixAtDepth[$depth - 1] ?? '') : '';
            $segment = str_pad((string) $counters[$depth], self::SEGMENT_WIDTH, '0', STR_PAD_LEFT);
            $path = $parentPrefix.$segment;

            $prefixAtDepth[$depth] = $path;
            $this->resetDeeperThan($prefixAtDepth, $depth);

            $paths[$id] = $path;
            $previousDepth = $depth;
        }

        return $paths;
    }

    /**
     * Validate the role-based placement legality of an ordered
     * `[id, depth, item_type]` list, throwing {@see InvalidArgumentException} on
     * the first violation.
     *
     * The parent of a node is the nearest preceding node at depth - 1 (null when
     * the node is at root depth 1). Depth is clamped identically to
     * {@see rebuild()} BEFORE parents are resolved, so an over-deep node is
     * validated against the parent it would actually land under. Rules:
     *
     *  - accessory : parent MUST be a product.
     *  - group | product | service | text : parent must be root (depth 1) OR a group.
     *
     * @param  array<int, array{id: int, depth: int, item_type: string}>  $nodes
     */
    public function validatePlacement(array $nodes): void
    {
        $previousDepth = 0;
        /** @var array<int, OpportunityItemType> $typeAtDepth role of the node currently occupying each depth */
        $typeAtDepth = [];

        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $depth = $this->clampDepth((int) $node['depth'], $previousDepth);
            $type = OpportunityItemType::from((string) $node['item_type']);

            $parentType = $depth > 1 ? ($typeAtDepth[$depth - 1] ?? null) : null;

            $this->assertPlacement($id, $type, $parentType);

            $typeAtDepth[$depth] = $type;
            $this->resetDeeperThan($typeAtDepth, $depth);

            $previousDepth = $depth;
        }
    }

    /**
     * Clamp a claimed depth to the legal range: at least 1, and at most one
     * level deeper than the previous node.
     */
    private function clampDepth(int $depth, int $previousDepth): int
    {
        return max(1, min($depth, $previousDepth + 1));
    }

    /**
     * Remove every entry in the keyed-by-depth map whose depth is greater than
     * the given depth.
     *
     * @template TValue
     *
     * @param  array<int, TValue>  $map
     */
    private function resetDeeperThan(array &$map, int $depth): void
    {
        foreach (array_keys($map) as $d) {
            if ($d > $depth) {
                unset($map[$d]);
            }
        }
    }

    /**
     * Assert that a node of the given role may legally sit under the resolved
     * parent role (null parent == root placement).
     *
     * @throws InvalidArgumentException when the placement breaks a role rule
     */
    private function assertPlacement(int $id, OpportunityItemType $type, ?OpportunityItemType $parentType): void
    {
        if ($type === OpportunityItemType::Accessory) {
            if ($parentType !== OpportunityItemType::Product) {
                throw new InvalidArgumentException(sprintf(
                    'Item %d (accessory) must be placed under a product, %s given.',
                    $id,
                    $parentType === null ? 'root' : $parentType->value,
                ));
            }

            return;
        }

        if ($parentType !== null && $parentType !== OpportunityItemType::Group) {
            throw new InvalidArgumentException(sprintf(
                'Item %d (%s) must be placed at root or under a group, %s given.',
                $id,
                $type->value,
                $parentType->value,
            ));
        }
    }
}
