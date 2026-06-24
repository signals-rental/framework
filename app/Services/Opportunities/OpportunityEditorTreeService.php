<?php

namespace App\Services\Opportunities;

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Path-tree helpers for the legacy opportunity line-item editor (`items.blade.php`).
 *
 * Groups are persisted {@see OpportunityItemType::Group} rows; child lines nest by
 * `path`. Auto-groups carry `custom_fields.auto_group_key` (mirroring the retired
 * `opportunity_sections.auto_group_key`).
 */
class OpportunityEditorTreeService
{
    public const int MAX_GROUP_DEPTH = 5;

    public const string AUTO_GROUP_KEY_FIELD = 'auto_group_key';

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @param  callable(OpportunityItem): array<string, mixed>  $buildLineRow
     * @return array<int, array<string, mixed>>
     */
    public function buildDisplayGroups(Collection $items, callable $buildLineRow): array
    {
        /** @var array<int, array<string, mixed>> $ordered */
        $ordered = [];
        /** @var array<string, int> $indexByPath */
        $indexByPath = [];
        $fallback = null;

        foreach ($items->sortBy('path')->values() as $item) {
            if ($item->item_type === OpportunityItemType::Group) {
                $autoKey = $this->autoGroupKey($item);
                $ordered[] = [
                    'key' => 'group:'.$item->id,
                    'kind' => $autoKey !== null ? 'auto' : 'group',
                    'group_id' => $item->id,
                    'path' => $item->path,
                    'parent_path' => $item->parentPath(),
                    'depth' => max(0, $item->depth() - 1),
                    'label' => $item->name,
                    'lines' => [],
                    'subtotal' => 0,
                ];
                $indexByPath[$item->path] = count($ordered) - 1;

                continue;
            }

            if ($item->item_type === OpportunityItemType::Accessory) {
                continue;
            }

            $parentPath = $item->parentPath();

            if ($parentPath !== null && isset($indexByPath[$parentPath])) {
                $index = $indexByPath[$parentPath];
                $ordered[$index]['lines'][] = $buildLineRow($item);
                $ordered[$index]['subtotal'] += (int) $item->total;

                continue;
            }

            if ($fallback === null) {
                $fallback = [
                    'key' => 'auto:ungrouped',
                    'kind' => 'auto',
                    'group_id' => null,
                    'path' => null,
                    'parent_path' => null,
                    'depth' => 0,
                    'label' => __('Ungrouped'),
                    'lines' => [],
                    'subtotal' => 0,
                ];
            }

            $fallback['lines'][] = $buildLineRow($item);
            $fallback['subtotal'] += (int) $item->total;
        }

        if ($fallback !== null) {
            $ordered[] = $fallback;
        }

        return $ordered;
    }

    /**
     * @param  array<int, Product>  $products
     */
    public function findOrCreateAutoGroup(Opportunity $opportunity, OpportunityItem $lineItem, array $products = []): OpportunityItem
    {
        [$key, $label] = app(OpportunityAutoGroupResolver::class)
            ->resolveLegacySectionKey($lineItem, $products);

        $existing = $this->findAutoGroupByKey($opportunity, $key);

        if ($existing !== null) {
            return $existing;
        }

        (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
            'name' => $label,
            'custom_fields' => [self::AUTO_GROUP_KEY_FIELD => $key],
        ]));

        return $this->findAutoGroupByKey($opportunity->fresh(['items']) ?? $opportunity, $key)
            ?? throw ValidationException::withMessages([
                'group' => 'The auto group could not be created.',
            ]);
    }

    public function autoGroupKey(OpportunityItem $group): ?string
    {
        if ($group->item_type !== OpportunityItemType::Group) {
            return null;
        }

        $fields = $group->custom_fields ?? [];
        $key = $fields[self::AUTO_GROUP_KEY_FIELD] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Resolve a destination key from the editor UI to a parent path for nesting lines.
     *
     * @param  Collection<int, OpportunityItem>  $items
     */
    public function parentPathForGroupKey(Collection $items, ?string $groupKey): ?string
    {
        if ($groupKey === null || $groupKey === '') {
            return null;
        }

        if (str_starts_with($groupKey, 'group:')) {
            $groupId = (int) substr($groupKey, strlen('group:'));
            $group = $items->firstWhere('id', $groupId);

            return $group?->path;
        }

        if (str_starts_with($groupKey, 'auto:')) {
            $group = $items
                ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group)
                ->first(fn (OpportunityItem $item): bool => $this->autoGroupKey($item) === $groupKey);

            return $group?->path;
        }

        return null;
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return list<array{id: int, depth: int}>
     */
    public function nodesAfterMovingLine(Collection $items, int $lineId, int $position, ?string $destinationGroupKey): array
    {
        $items = $items->keyBy('id');
        /** @var OpportunityItem $line */
        $line = $items->get($lineId);

        $targetParentPath = $this->parentPathForGroupKey($items, $destinationGroupKey);

        /** @var list<OpportunityItem> $groups */
        $groups = $items->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group)
            ->sortBy('path')
            ->values()
            ->all();

        /** @var list<OpportunityItem> $movableLines */
        $movableLines = $items->filter(fn (OpportunityItem $item): bool => $this->isMovableLine($item))
            ->sortBy('path')
            ->values()
            ->all();

        $siblings = array_values(array_filter(
            $movableLines,
            fn (OpportunityItem $item): bool => $item->id !== $lineId && $item->parentPath() === $targetParentPath,
        ));

        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$line]);

        $linesByParent = [];
        $linesByParent[$this->parentKey($targetParentPath)] = $siblings;

        foreach ($movableLines as $movableLine) {
            if ($movableLine->id === $lineId) {
                continue;
            }

            $parentKey = $this->parentKey($movableLine->parentPath());

            if (isset($linesByParent[$parentKey])) {
                continue;
            }

            $linesByParent[$parentKey] = array_values(array_filter(
                $movableLines,
                fn (OpportunityItem $item): bool => $item->parentPath() === $movableLine->parentPath(),
            ));
        }

        $groupsByParent = $this->buildGroupsByParent($groups);

        return $this->flattenTree($groupsByParent, $linesByParent, $items);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return list<array{id: int, depth: int}>
     */
    public function nodesAfterMovingGroup(Collection $items, int $groupId, int $position, ?string $parentGroupKey): array
    {
        $items = $items->keyBy('id');
        /** @var OpportunityItem $moved */
        $moved = $items->get($groupId);

        if ($moved->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'The group could not be found.',
            ]);
        }

        $targetParentPath = $this->parentPathFromParentGroupKey($items, $parentGroupKey);

        if ($targetParentPath !== null) {
            $this->assertCanNestUnder($targetParentPath);
        }

        if ($targetParentPath !== null && str_starts_with($targetParentPath, $moved->path)) {
            throw ValidationException::withMessages([
                'nodes' => 'A group cannot be nested under its own subtree.',
            ]);
        }

        /** @var list<OpportunityItem> $groups */
        $groups = $items->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group)
            ->sortBy('path')
            ->values()
            ->all();

        $siblings = array_values(array_filter(
            $groups,
            fn (OpportunityItem $group): bool => $group->id !== $groupId && $group->parentPath() === $targetParentPath,
        ));

        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$moved]);

        $groupsByParent = [];
        $groupsByParent[$this->parentKey($targetParentPath)] = $siblings;

        foreach ($groups as $group) {
            if ($group->id === $groupId) {
                continue;
            }

            $parentKey = $this->parentKey($group->parentPath());

            if (isset($groupsByParent[$parentKey])) {
                continue;
            }

            $groupsByParent[$parentKey] = array_values(array_filter(
                $groups,
                fn (OpportunityItem $candidate): bool => $candidate->parentPath() === $group->parentPath(),
            ));
        }

        /** @var list<OpportunityItem> $movableLines */
        $movableLines = $items->filter(fn (OpportunityItem $item): bool => $this->isMovableLine($item))
            ->sortBy('path')
            ->values()
            ->all();

        $linesByParent = [];
        foreach ($movableLines as $line) {
            $parentKey = $this->parentKey($line->parentPath());

            if (isset($linesByParent[$parentKey])) {
                continue;
            }

            $linesByParent[$parentKey] = array_values(array_filter(
                $movableLines,
                fn (OpportunityItem $candidate): bool => $candidate->parentPath() === $line->parentPath(),
            ));
        }

        return $this->flattenTree($groupsByParent, $linesByParent, $items);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return list<array{id: int, depth: int}>
     */
    public function nodesAfterDissolvingGroup(Collection $items, int $groupId): array
    {
        $items = $items->keyBy('id');
        /** @var OpportunityItem $group */
        $group = $items->get($groupId);

        if ($group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'The group could not be found.',
            ]);
        }

        $groupPath = $group->path;

        /** @var list<OpportunityItem> $groups */
        $groups = $items->filter(
            fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group && $item->id !== $groupId,
        )->sortBy('path')->values()->all();

        /** @var list<OpportunityItem> $movableLines */
        $movableLines = $items->filter(fn (OpportunityItem $item): bool => $this->isMovableLine($item))
            ->sortBy('path')
            ->values()
            ->all();

        $promoted = [];
        $remaining = [];

        foreach ($movableLines as $line) {
            if ($line->parentPath() === $groupPath) {
                $promoted[] = $line;

                continue;
            }

            $remaining[] = $line;
        }

        $linesByParent = [];
        $linesByParent[''] = $promoted;

        foreach ($remaining as $line) {
            $parentKey = $this->parentKey($line->parentPath());

            if (isset($linesByParent[$parentKey])) {
                continue;
            }

            $linesByParent[$parentKey] = array_values(array_filter(
                $remaining,
                fn (OpportunityItem $candidate): bool => $candidate->parentPath() === $line->parentPath(),
            ));
        }

        $groupsByParent = [];
        foreach ($groups as $candidate) {
            $parentKey = $this->parentKey($candidate->parentPath());

            if (isset($groupsByParent[$parentKey])) {
                continue;
            }

            $groupsByParent[$parentKey] = array_values(array_filter(
                $groups,
                fn (OpportunityItem $groupRow): bool => $groupRow->parentPath() === $candidate->parentPath(),
            ));
        }

        $nodes = $this->flattenTree($groupsByParent, $linesByParent, $items);
        $nodes[] = ['id' => $groupId, 'depth' => $group->depth()];

        return $this->dedupeNodes($nodes);
    }

    /**
     * @param  list<array{id: int, depth: int}>  $nodes
     */
    public function restructure(Opportunity $opportunity, array $nodes): void
    {
        if ($nodes === []) {
            return;
        }

        (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
            'nodes' => $nodes,
        ]));
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return array<int, array{value: string, label: string}>
     */
    public function parentGroupOptions(Collection $items): array
    {
        $options = [['value' => '', 'label' => '— Top level —']];

        foreach ($this->groupRowsPreOrder($items) as $entry) {
            $group = $entry['group'];
            $options[] = [
                'value' => (string) $group->id,
                'label' => str_repeat('— ', $entry['depth']).$group->name,
            ];
        }

        return $options;
    }

    public function assertCanNestUnder(?string $parentPath): void
    {
        if ($parentPath === null || $parentPath === '') {
            return;
        }

        $parentDepth = (int) (strlen($parentPath) / 4);

        if ($parentDepth + 1 > self::MAX_GROUP_DEPTH) {
            throw ValidationException::withMessages([
                'parent_path' => 'Groups cannot be nested more than '.self::MAX_GROUP_DEPTH.' levels deep.',
            ]);
        }
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     */
    public function parentPathForGroupId(Collection $items, ?int $groupId): ?string
    {
        if ($groupId === null) {
            return null;
        }

        $group = $items->firstWhere('id', $groupId);

        if ($group === null || $group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'parent_id' => 'The parent group does not belong to this opportunity.',
            ]);
        }

        return $group->path;
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return list<array{group: OpportunityItem, depth: int}>
     */
    public function groupRowsPreOrder(Collection $items): array
    {
        /** @var Collection<int, OpportunityItem> $groups */
        $groups = $items->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

        $byParent = $groups
            ->sortBy('path')
            ->groupBy(fn (OpportunityItem $group): string => $this->parentKey($group->parentPath()));

        $ordered = [];

        $walk = function (?string $parentPath, int $depth) use (&$walk, $byParent, &$ordered): void {
            $parentKey = $this->parentKey($parentPath);

            foreach ($byParent->get($parentKey, collect()) as $group) {
                $ordered[] = ['group' => $group, 'depth' => $depth];
                $walk($group->path, $depth + 1);
            }
        };

        $walk(null, 0);

        return $ordered;
    }

    private function findAutoGroupByKey(Opportunity $opportunity, string $key): ?OpportunityItem
    {
        return $opportunity->items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group)
            ->first(fn (OpportunityItem $item): bool => $this->autoGroupKey($item) === $key);
    }

    private function isMovableLine(OpportunityItem $item): bool
    {
        return in_array($item->item_type, [
            OpportunityItemType::Product,
            OpportunityItemType::Service,
        ], true);
    }

    private function parentKey(?string $parentPath): string
    {
        return $parentPath ?? '';
    }

    /**
     * @param  array<string, list<OpportunityItem>>  $groupsByParent
     * @param  array<string, list<OpportunityItem>>  $linesByParent
     * @param  Collection<int, OpportunityItem>  $items
     * @return list<array{id: int, depth: int}>
     */
    private function flattenTree(array $groupsByParent, array $linesByParent, Collection $items): array
    {
        $nodes = [];

        $appendSubtree = function (?string $parentPath, int $depth) use (&$appendSubtree, $groupsByParent, $linesByParent, $items, &$nodes): void {
            $parentKey = $this->parentKey($parentPath);

            foreach ($groupsByParent[$parentKey] ?? [] as $group) {
                $nodes[] = ['id' => $group->id, 'depth' => $depth + 1];

                foreach ($linesByParent[$group->path] ?? [] as $line) {
                    $nodes[] = ['id' => $line->id, 'depth' => $depth + 2];
                    $this->appendAccessories($items, $line, $depth + 3, $nodes);
                }

                $appendSubtree($group->path, $depth + 1);
            }

            if ($parentPath === null) {
                foreach ($linesByParent[''] ?? [] as $line) {
                    $nodes[] = ['id' => $line->id, 'depth' => 1];
                    $this->appendAccessories($items, $line, 2, $nodes);
                }
            }
        };

        $appendSubtree(null, 0);

        return $this->dedupeNodes($nodes);
    }

    /**
     * @param  list<OpportunityItem>  $groups
     * @return array<string, list<OpportunityItem>>
     */
    private function buildGroupsByParent(array $groups): array
    {
        $groupsByParent = [];

        foreach ($groups as $group) {
            $parentKey = $this->parentKey($group->parentPath());

            if (isset($groupsByParent[$parentKey])) {
                continue;
            }

            $groupsByParent[$parentKey] = array_values(array_filter(
                $groups,
                fn (OpportunityItem $candidate): bool => $candidate->parentPath() === $group->parentPath(),
            ));
        }

        return $groupsByParent;
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @param  list<array{id: int, depth: int}>  $nodes
     */
    private function appendAccessories(Collection $items, OpportunityItem $line, int $depth, array &$nodes): void
    {
        $accessories = $items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Accessory
                && $item->parentPath() === $line->path)
            ->sortBy('path')
            ->values();

        foreach ($accessories as $accessory) {
            $nodes[] = ['id' => $accessory->id, 'depth' => $depth];
        }
    }

    /**
     * @param  list<array{id: int, depth: int}>  $nodes
     * @return list<array{id: int, depth: int}>
     */
    private function dedupeNodes(array $nodes): array
    {
        $seen = [];
        $deduped = [];

        foreach ($nodes as $node) {
            if (isset($seen[$node['id']])) {
                continue;
            }

            $seen[$node['id']] = true;
            $deduped[] = $node;
        }

        return $deduped;
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function parentPathFromParentGroupKey(Collection $items, ?string $parentGroupKey): ?string
    {
        if ($parentGroupKey === null || $parentGroupKey === 'group-parent:root') {
            return null;
        }

        if (str_starts_with($parentGroupKey, 'group-parent:')) {
            $value = substr($parentGroupKey, strlen('group-parent:'));

            if ($value === 'root') {
                return null;
            }

            $groupId = (int) $value;
            $group = $items->firstWhere('id', $groupId);

            if ($group === null || $group->item_type !== OpportunityItemType::Group) {
                throw ValidationException::withMessages([
                    'nodes' => 'The destination parent group could not be found.',
                ]);
            }

            return $group->path;
        }

        return null;
    }
}
