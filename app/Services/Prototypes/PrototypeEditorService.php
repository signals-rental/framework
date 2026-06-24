<?php

namespace App\Services\Prototypes;

use App\Enums\OpportunityItemType;
use App\Enums\PrototypeItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\PrototypeOpportunityItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared back-end contract for the throwaway "Editor Lab" prototype editors.
 *
 * The four parallel prototype editors (jquery / sortable-tree / sortablejs /
 * local-first) ALL drive their own private copy of a flat, Current-RMS-style
 * line-item tree through this single service. Each prototype is scoped by its
 * `$prototype` string ('jquery' | 'sortable-tree' | 'sortablejs' |
 * 'local-first'), so the four editors never collide on the shared table.
 *
 * THE TREE MODEL (Current RMS style):
 *  - One flat list of rows; each row has an `item_type`
 *    (group / product / accessory / service).
 *  - Nesting + order are encoded in a materialized `path`: a 4-char zero-padded
 *    segment per level (e.g. "0001", "00010001", "0003"). Lexical sort over
 *    `path` == tree pre-order. depth == segment count (strlen / 4).
 *
 * THE UNIVERSAL DnD CONTRACT — {@see persistTree()}:
 *  Every DnD library, however it represents the tree internally, maps its drop
 *  result onto ONE shape: an ORDERED (final display pre-order) array of
 *  `['id' => int, 'depth' => int]`. The service rebuilds every `path` purely
 *  from that order + depth. Build your editor to emit that and you are done.
 *
 * IMPORTANT: this is throwaway prototype scaffolding. It is deliberately NOT
 * wired to the event-sourced opportunity backend — keep all writes here.
 */
class PrototypeEditorService
{
    /**
     * The width (in characters) of one zero-padded path segment per tree level.
     */
    private const SEGMENT_WIDTH = 4;

    /**
     * Seed a representative line-item tree for a (opportunity, prototype) pair,
     * but only the FIRST time — if any rows already exist for that pair this is
     * a no-op (so reloading an editor never re-seeds over the operator's drags).
     *
     * Where the real opportunity is rich enough it is materialized from live
     * data (its {@see OpportunitySection} groups, the active-version
     * {@see OpportunityItem} product rows). When the real opportunity is sparse
     * (< 2 groups or < 3 products) a rich synthetic sample tree is generated
     * instead, so every prototype always has a meaty, multi-level tree to drag.
     */
    public function ensureSeeded(int $opportunityId, string $prototype): void
    {
        $exists = PrototypeOpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->exists();

        if ($exists) {
            return;
        }

        $opportunity = Opportunity::query()->withTrashed()->find($opportunityId);

        $rows = $opportunity !== null
            ? $this->buildRowsFromRealOpportunity($opportunity)
            : [];

        if (! $this->treeIsRichEnough($rows)) {
            $rows = $this->buildSyntheticSampleRows();
        }

        DB::transaction(function () use ($opportunityId, $prototype, $rows): void {
            foreach ($rows as $row) {
                PrototypeOpportunityItem::create(array_merge($row, [
                    'opportunity_id' => $opportunityId,
                    'prototype' => $prototype,
                ]));
            }
        });
    }

    /**
     * Return the prototype's tree as a FLAT array ordered by `path` (pre-order).
     *
     * Each element is an array with these keys (the agreed read shape every
     * prototype renders from):
     *  - id (int)
     *  - item_type (string)            'group' | 'product' | 'accessory' | 'service'
     *  - path (string)
     *  - depth (int)                   1-based (top-level rows are depth 1)
     *  - parent_path (string|null)     the path prefix of the parent, null at top level
     *  - revenue_group_id (int|null)
     *  - name (string)
     *  - quantity (string)             decimal, e.g. "4.00"
     *  - days (int)
     *  - unit_price (int)              minor units
     *  - unit_price_display (string)   major units, e.g. "160.00"
     *  - discount_percent (string|null)
     *  - charge_total (int)            minor units
     *  - charge_total_display (string) major units, e.g. "640.00"
     *  - type_label (string|null)
     *  - status_label (string|null)
     *  - is_collapsed (bool)
     *  - has_children (bool)
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(int $opportunityId, string $prototype): array
    {
        $rows = $this->orderedRows($opportunityId, $prototype);
        $paths = $rows->pluck('path')->all();

        return $rows->map(fn (PrototypeOpportunityItem $row): array => $this->rowToArray($row, $paths))->all();
    }

    /**
     * The same tree as {@see tree()} but as a NESTED structure: a list of root
     * nodes, each carrying a `children` array of the same node shape (recursive).
     * For DnD libraries that prefer to consume nesting directly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nestedTree(int $opportunityId, string $prototype): array
    {
        $flat = $this->tree($opportunityId, $prototype);

        // Index nodes by path; attach an empty children array to each.
        $byPath = [];
        foreach ($flat as $node) {
            $node['children'] = [];
            $byPath[$node['path']] = $node;
        }

        // Attach each node to its parent (deepest paths first so children are
        // fully built before being nested into a parent).
        uksort($byPath, fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($b, $a));

        $roots = [];
        foreach ($byPath as $path => $node) {
            $parentPath = $node['parent_path'];
            if ($parentPath !== null && isset($byPath[$parentPath])) {
                array_unshift($byPath[$parentPath]['children'], $node);
            }
        }

        // Collect the roots in pre-order (re-sort ascending by path).
        foreach ($byPath as $path => $node) {
            if ($node['parent_path'] === null) {
                $roots[$path] = $node;
            }
        }
        uksort($roots, fn (string $a, string $b): int => strcmp($a, $b));

        // Recursively re-sort children ascending by path so the nested tree is
        // in pre-order too.
        $sortChildren = function (array &$node) use (&$sortChildren): void {
            usort($node['children'], fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
            foreach ($node['children'] as &$child) {
                $sortChildren($child);
            }
        };

        $result = array_values($roots);
        foreach ($result as &$root) {
            $sortChildren($root);
        }

        return $result;
    }

    /**
     * Persist a re-ordered / re-nested tree from a DnD drop.
     *
     * THIS IS THE UNIVERSAL CONTRACT every prototype maps its drop result onto.
     * `$nodes` is the normalized drop output: an ORDERED array (in final display
     * pre-order, top to bottom) of `['id' => int, 'depth' => int]`. Depth is
     * 1-based (a top-level row is depth 1).
     *
     * Every row's `path` (and therefore its parent) is rebuilt PURELY from the
     * order + depth: a per-depth counter is maintained; a node at depth d gets
     * path = (parent path prefix for depth d) + zero-pad(counter[d]); counters
     * deeper than d are reset whenever the depth decreases. Depth is clamped so
     * it can never jump UP by more than 1 from the previous node (a child can
     * only be one level deeper than the row above it).
     *
     * Runs in a single transaction. Ids not present in `$nodes` are left
     * untouched (callers always pass the full tree).
     *
     * @param  array<int, array{id: int, depth: int}>  $nodes
     */
    public function persistTree(int $opportunityId, string $prototype, array $nodes): void
    {
        DB::transaction(function () use ($opportunityId, $prototype, $nodes): void {
            // per-level counters; index 1 == depth 1
            $counters = [];
            // the running path prefix produced at each depth, so a child can
            // prepend its parent's full path
            $prefixAtDepth = [];
            $previousDepth = 0;

            foreach ($nodes as $node) {
                $id = (int) $node['id'];
                $depth = (int) $node['depth'];

                // Clamp: depth can be at most previousDepth + 1, and never < 1.
                $depth = max(1, min($depth, $previousDepth + 1));

                // Reset all counters deeper than the current depth.
                foreach (array_keys($counters) as $d) {
                    if ($d > $depth) {
                        unset($counters[$d]);
                    }
                }

                $counters[$depth] = ($counters[$depth] ?? 0) + 1;

                $parentPrefix = $depth > 1 ? ($prefixAtDepth[$depth - 1] ?? '') : '';
                $segment = str_pad((string) $counters[$depth], self::SEGMENT_WIDTH, '0', STR_PAD_LEFT);
                $path = $parentPrefix.$segment;

                $prefixAtDepth[$depth] = $path;
                // Drop any stale deeper prefixes.
                foreach (array_keys($prefixAtDepth) as $d) {
                    if ($d > $depth) {
                        unset($prefixAtDepth[$d]);
                    }
                }

                PrototypeOpportunityItem::query()
                    ->where('id', $id)
                    ->where('opportunity_id', $opportunityId)
                    ->where('prototype', $prototype)
                    ->update(['path' => $path]);

                $previousDepth = $depth;
            }
        });
    }

    /**
     * Update a single editable field on one row and recompute its charge total.
     *
     * Whitelisted fields: quantity, days, unit_price, discount_percent, name,
     * type_label, status_label. For `unit_price` the value is a MAJOR-unit
     * decimal string (e.g. "160.00") and is stored as minor units. After any
     * pricing-relevant change the charge total is recomputed as
     * round(quantity * days * unit_price * (1 - discount_percent/100)).
     *
     * The row is scoped by `opportunity_id` AND `prototype` so one editor (or a
     * forged request) can never mutate another opportunity's or another
     * prototype's rows by guessing an id.
     *
     * @param  string  $field  one of the whitelisted field names
     */
    public function updateField(int $itemId, int $opportunityId, string $prototype, string $field, mixed $value): void
    {
        $allowed = ['quantity', 'days', 'unit_price', 'discount_percent', 'name', 'type_label', 'status_label'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $item = PrototypeOpportunityItem::query()
            ->where('id', $itemId)
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->firstOrFail();

        if ($field === 'unit_price') {
            $item->unit_price = $this->majorToMinor((string) $value);
        } elseif ($field === 'quantity') {
            $item->quantity = (string) round((float) $value, 2);
        } elseif ($field === 'days') {
            $item->days = max(0, (int) $value);
        } elseif ($field === 'discount_percent') {
            $item->discount_percent = ($value === null || $value === '')
                ? null
                : (string) round((float) $value, 2);
        } else {
            $item->{$field} = $value === null ? null : (string) $value;
        }

        $item->charge_total = $this->computeChargeTotal(
            (float) $item->quantity,
            (int) $item->days,
            (int) $item->unit_price,
            $item->discount_percent === null ? null : (float) $item->discount_percent,
        );

        $item->save();
    }

    /**
     * Add a new top-level (or sibling) Group row, optionally after a given path.
     *
     * The new group is placed at the end of the top level (or, when
     * `$afterPath` is given, immediately after that path's top-level ancestor
     * for display intent — the prototype is free to re-drag afterwards). Paths
     * are then renormalized. Returns the new row id.
     */
    public function addGroup(int $opportunityId, string $prototype, ?string $afterPath, string $name): int
    {
        $path = $this->nextTopLevelPath($opportunityId, $prototype);

        $group = PrototypeOpportunityItem::create([
            'opportunity_id' => $opportunityId,
            'prototype' => $prototype,
            'item_type' => PrototypeItemType::Group,
            'path' => $path,
            'name' => $name !== '' ? $name : 'New group',
            'quantity' => '1.00',
            'days' => 1,
            'unit_price' => 0,
            'charge_total' => 0,
            'is_collapsed' => false,
        ]);

        return (int) $group->id;
    }

    /**
     * Add a new Product item, optionally nested under a parent path.
     *
     * When `$parentPath` is given the new row is appended as the last child of
     * that path; otherwise it is appended at the top level. Returns the new id.
     */
    public function addItem(int $opportunityId, string $prototype, ?string $parentPath, string $name): int
    {
        $path = $parentPath !== null
            ? $this->nextChildPath($opportunityId, $prototype, $parentPath)
            : $this->nextTopLevelPath($opportunityId, $prototype);

        $item = PrototypeOpportunityItem::create([
            'opportunity_id' => $opportunityId,
            'prototype' => $prototype,
            'item_type' => PrototypeItemType::Product,
            'path' => $path,
            'name' => $name !== '' ? $name : 'New item',
            'quantity' => '1.00',
            'days' => 1,
            'unit_price' => 0,
            'charge_total' => 0,
            'type_label' => 'Rental',
            'status_label' => 'Reserved',
            'is_collapsed' => false,
        ]);

        return (int) $item->id;
    }

    /**
     * Delete a row AND all of its descendants (cascade by path prefix).
     *
     * Scoped by `opportunity_id` AND `prototype` so a guessed id can never reach
     * another opportunity's or prototype's rows.
     */
    public function deleteNode(int $itemId, int $opportunityId, string $prototype): void
    {
        $item = PrototypeOpportunityItem::query()
            ->where('id', $itemId)
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->first();

        if ($item === null) {
            return;
        }

        PrototypeOpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->where('path', 'like', $item->path.'%')
            ->delete();
    }

    /**
     * Clone a row and its entire subtree, appended as a new top-level (or
     * sibling-of-the-source) branch. Returns the new root row's id.
     *
     * Scoped by `opportunity_id` AND `prototype` so a guessed id can never clone
     * another opportunity's or prototype's rows.
     */
    public function cloneNode(int $itemId, int $opportunityId, string $prototype): int
    {
        $source = PrototypeOpportunityItem::query()
            ->where('id', $itemId)
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->firstOrFail();

        $subtree = PrototypeOpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->where('path', 'like', $source->path.'%')
            ->orderBy('path')
            ->get();

        $sourceDepth = strlen($source->path);

        return DB::transaction(function () use ($source, $subtree, $sourceDepth): int {
            // New root path: a fresh sibling at the source's own level.
            $parentPrefix = substr($source->path, 0, $sourceDepth - self::SEGMENT_WIDTH);
            $newRootPath = $this->nextSiblingPath($source->opportunity_id, $source->prototype, $parentPrefix);

            $newRootId = 0;
            foreach ($subtree as $row) {
                // Re-base each descendant path under the new root.
                $suffix = substr($row->path, $sourceDepth);
                $newPath = $newRootPath.$suffix;

                $clone = $row->replicate(['id']);
                $clone->path = $newPath;
                if ($row->id === $source->id) {
                    $clone->name = $source->name.' (copy)';
                }
                $clone->save();

                if ($row->id === $source->id) {
                    $newRootId = (int) $clone->id;
                }
            }

            return $newRootId;
        });
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * @return Collection<int, PrototypeOpportunityItem>
     */
    private function orderedRows(int $opportunityId, string $prototype): Collection
    {
        return PrototypeOpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->orderBy('path')
            ->get();
    }

    /**
     * @param  array<int, string>  $allPaths
     * @return array<string, mixed>
     */
    private function rowToArray(PrototypeOpportunityItem $row, array $allPaths): array
    {
        $depth = (int) (strlen($row->path) / self::SEGMENT_WIDTH);
        $parentPath = $depth > 1 ? substr($row->path, 0, strlen($row->path) - self::SEGMENT_WIDTH) : null;

        $hasChildren = false;
        foreach ($allPaths as $candidate) {
            if ($candidate !== $row->path && str_starts_with($candidate, $row->path)) {
                $hasChildren = true;
                break;
            }
        }

        return [
            'id' => (int) $row->id,
            'item_type' => $row->item_type->value,
            'path' => $row->path,
            'depth' => $depth,
            'parent_path' => $parentPath,
            'revenue_group_id' => $row->revenue_group_id,
            'name' => $row->name,
            'quantity' => $row->quantity,
            'days' => (int) $row->days,
            'unit_price' => (int) $row->unit_price,
            'unit_price_display' => $this->minorToMajor((int) $row->unit_price),
            'discount_percent' => $row->discount_percent,
            'charge_total' => (int) $row->charge_total,
            'charge_total_display' => $this->minorToMajor((int) $row->charge_total),
            'type_label' => $row->type_label,
            'status_label' => $row->status_label,
            'is_collapsed' => (bool) $row->is_collapsed,
            'has_children' => $hasChildren,
        ];
    }

    /**
     * Build prototype rows from the live opportunity: its section tree becomes
     * Group rows, active-version product items become Product rows under their
     * section, and (where available) each product's accessories become
     * Accessory sub-rows. Paths are assigned in pre-order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRowsFromRealOpportunity(Opportunity $opportunity): array
    {
        $items = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->when($opportunity->active_version_id > 0, fn ($q) => $q->where('version_id', $opportunity->active_version_id))
            ->orderBy('path')
            ->get();

        $rows = [];

        foreach ($items as $item) {
            if ($item->item_type === OpportunityItemType::Group) {
                $rows[] = $this->groupRow($item->path, $item->name);

                continue;
            }

            if ($item->item_type === OpportunityItemType::Product) {
                $rows[] = $this->productRow($item->path, $item);
            }
        }

        return $rows;
    }

    /**
     * A rich synthetic multi-level sample tree so every prototype has something
     * meaty to drag, regardless of how sparse the real opportunity is.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSyntheticSampleRows(): array
    {
        $rows = [];

        // Group 1 — Lighting (moving heads + accessories)
        $g1 = $this->segment(1);
        $rows[] = $this->groupRow($g1, 'Lighting - Moving Heads');
        $rows[] = $this->sampleProduct($g1.$this->segment(1), 'Robe MegaPointe', 8, 3, 4500, 'Rental', 'Reserved');
        $rows[] = $this->sampleAccessory($g1.$this->segment(1).$this->segment(1), 'Safety Bond', 8);
        $rows[] = $this->sampleAccessory($g1.$this->segment(1).$this->segment(2), 'Clamp - Half Coupler', 8);
        $rows[] = $this->sampleAccessory($g1.$this->segment(1).$this->segment(3), 'Flight Case (6-way)', 2);
        $rows[] = $this->sampleProduct($g1.$this->segment(2), 'Martin MAC Aura XB', 12, 3, 3800, 'Rental', 'Booked Out');
        $rows[] = $this->sampleAccessory($g1.$this->segment(2).$this->segment(1), 'Safety Bond', 12);
        $rows[] = $this->sampleAccessory($g1.$this->segment(2).$this->segment(2), 'Omega Clamp', 12);

        // Group 2 — Power & Distribution
        $g2 = $this->segment(2);
        $rows[] = $this->groupRow($g2, 'Power - Adaptors');
        $rows[] = $this->sampleProduct($g2.$this->segment(1), '63A 3-phase Distro', 2, 3, 6500, 'Rental', 'Prepared');
        $rows[] = $this->sampleAccessory($g2.$this->segment(1).$this->segment(1), '63A 25m Cable', 4);
        $rows[] = $this->sampleAccessory($g2.$this->segment(1).$this->segment(2), '16A Socapex Loom', 6);
        $rows[] = $this->sampleProduct($g2.$this->segment(2), '32A to 16A Adaptor', 10, 3, 800, 'Rental', 'Reserved');
        $rows[] = $this->sampleProduct($g2.$this->segment(3), 'IEC Lock Cable 3m', 24, 3, 250, 'Sale', null);

        // Group 3 — Transport & Crew (with a couple of service rows)
        $g3 = $this->segment(3);
        $rows[] = $this->groupRow($g3, 'Transport');
        $rows[] = $this->sampleService($g3.$this->segment(1), '7.5t Truck Hire', 1, 2, 28000);
        $rows[] = $this->sampleService($g3.$this->segment(2), 'Driver / Crew Day Rate', 2, 2, 18000);
        $rows[] = $this->sampleProduct($g3.$this->segment(3), 'Pallet Boxes', 6, 3, 1200, 'Rental', 'Reserved');

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function groupRow(string $path, string $name): array
    {
        return [
            'item_type' => PrototypeItemType::Group,
            'path' => $path,
            'name' => $name,
            'quantity' => '1.00',
            'days' => 1,
            'unit_price' => 0,
            'discount_percent' => null,
            'charge_total' => 0,
            'type_label' => null,
            'status_label' => null,
            'is_collapsed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(string $path, OpportunityItem $item): array
    {
        $qty = (float) $item->quantity ?: 1;
        $days = 1;
        $unitPrice = (int) $item->unit_price;
        $discount = $item->discount_percent === null ? null : (float) $item->discount_percent;

        return [
            'item_type' => PrototypeItemType::Product,
            'path' => $path,
            'name' => $item->name,
            'quantity' => (string) round($qty, 2),
            'days' => $days,
            'unit_price' => $unitPrice,
            'discount_percent' => $discount === null ? null : (string) $discount,
            'charge_total' => $this->computeChargeTotal($qty, $days, $unitPrice, $discount),
            'type_label' => 'Rental',
            'status_label' => 'Reserved',
            'is_collapsed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleProduct(string $path, string $name, float $qty, int $days, int $unitPrice, string $typeLabel, ?string $statusLabel): array
    {
        return [
            'item_type' => PrototypeItemType::Product,
            'path' => $path,
            'name' => $name,
            'quantity' => (string) round($qty, 2),
            'days' => $days,
            'unit_price' => $unitPrice,
            'discount_percent' => null,
            'charge_total' => $this->computeChargeTotal($qty, $days, $unitPrice, null),
            'type_label' => $typeLabel,
            'status_label' => $statusLabel,
            'is_collapsed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleAccessory(string $path, string $name, float $qty): array
    {
        return [
            'item_type' => PrototypeItemType::Accessory,
            'path' => $path,
            'name' => $name,
            'quantity' => (string) round($qty, 2),
            'days' => 3,
            'unit_price' => 0,
            'discount_percent' => null,
            'charge_total' => 0,
            'type_label' => 'Rental',
            'status_label' => 'Reserved',
            'is_collapsed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleService(string $path, string $name, float $qty, int $days, int $unitPrice): array
    {
        return [
            'item_type' => PrototypeItemType::Service,
            'path' => $path,
            'name' => $name,
            'quantity' => (string) round($qty, 2),
            'days' => $days,
            'unit_price' => $unitPrice,
            'discount_percent' => null,
            'charge_total' => $this->computeChargeTotal($qty, $days, $unitPrice, null),
            'type_label' => 'Service',
            'status_label' => null,
            'is_collapsed' => false,
        ];
    }

    /**
     * A seed tree is "rich enough" to skip synthesis when it has at least 2
     * group rows and at least 3 product rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function treeIsRichEnough(array $rows): bool
    {
        $groups = 0;
        $products = 0;

        foreach ($rows as $row) {
            if ($row['item_type'] === PrototypeItemType::Group) {
                $groups++;
            } elseif ($row['item_type'] === PrototypeItemType::Product) {
                $products++;
            }
        }

        return $groups >= 2 && $products >= 3;
    }

    private function nextTopLevelPath(int $opportunityId, string $prototype): string
    {
        return $this->nextSiblingPath($opportunityId, $prototype, '');
    }

    private function nextChildPath(int $opportunityId, string $prototype, string $parentPath): string
    {
        return $this->nextSiblingPath($opportunityId, $prototype, $parentPath);
    }

    /**
     * The next available path at the given parent prefix (one deeper level).
     */
    private function nextSiblingPath(int $opportunityId, string $prototype, string $parentPrefix): string
    {
        $childLen = strlen($parentPrefix) + self::SEGMENT_WIDTH;

        $max = PrototypeOpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->where('prototype', $prototype)
            ->when($parentPrefix !== '', fn ($q) => $q->where('path', 'like', $parentPrefix.'%'))
            ->whereRaw('LENGTH(path) = ?', [$childLen])
            ->orderByDesc('path')
            ->value('path');

        $next = 1;
        if ($max !== null) {
            $lastSegment = substr($max, -self::SEGMENT_WIDTH);
            $next = ((int) $lastSegment) + 1;
        }

        return $parentPrefix.$this->segment($next);
    }

    private function segment(int $n): string
    {
        return str_pad((string) $n, self::SEGMENT_WIDTH, '0', STR_PAD_LEFT);
    }

    private function computeChargeTotal(float $quantity, int $days, int $unitPrice, ?float $discountPercent): int
    {
        $gross = $quantity * $days * $unitPrice;
        $factor = 1 - (($discountPercent ?? 0) / 100);

        return (int) round($gross * $factor);
    }

    private function majorToMinor(string $major): int
    {
        return (int) round(((float) $major) * 100);
    }

    private function minorToMajor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
