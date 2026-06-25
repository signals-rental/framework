<?php

use App\Contracts\Opportunities\OpportunityLineItemsEditorContract;
use App\Actions\Opportunities\AddOpportunityAccessory;
use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\MergeOpportunityItems;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Data\Opportunities\AddOpportunityAccessoryData;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SubstituteItemData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Opportunities\LineItemTreeReconciler;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use App\Services\Opportunities\OpportunityLineItemTreeRevision;
use App\Services\Opportunities\ProductSearchService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;

/**
 * Production local-first line-item editor (Phase 5). Embedded in the opportunity
 * Show overview; Alpine owns the table under wire:ignore, Livewire exposes the
 * server contract only.
 */
new class extends Component implements OpportunityLineItemsEditorContract
{
    public Opportunity $opportunity;

    /** @var array<int, array<string, mixed>> */
    public array $catalogue = [];

    public string $quickAddDestination = '';

    public bool $editable = false;

    public string $newSectionName = '';

    public string $newSectionParent = '';

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->editable = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();
        $this->catalogue = app(ProductSearchService::class)->catalogueIndex($opportunity->store_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function tree(): array
    {
        return app(OpportunityLineItemsTreeBuilder::class)->tree($this->opportunity);
    }

    public function treeRevision(): int
    {
        return app(OpportunityLineItemTreeRevision::class)->current($this->opportunity->id);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function destinations(): array
    {
        return app(OpportunityLineItemsTreeBuilder::class)->destinations($this->opportunity);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function sectionOptions(): array
    {
        return app(OpportunityLineItemsTreeBuilder::class)->parentGroupOptions($this->opportunity);
    }

    /** @return array<int, array{value: string, label: string}> */
    #[Computed]
    public function parentGroupOptions(): array
    {
        return $this->sectionOptions;
    }

    #[Renderless]
    public function searchProducts(string $query): array
    {
        Gate::authorize('products.read');

        return app(ProductSearchService::class)
            ->search($query, $this->opportunity->store_id, 12)
            ->map(fn ($result) => $result->toArray())
            ->all();
    }

    public function addProduct(int $productId, float $quantity = 1, ?string $destination = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($productId, $quantity, $destination): void {
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The selected product could not be found.',
                ]);
            }

            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $parentPath = $this->resolveParentPathForAdd(
                $opportunity,
                $opportunity->items,
                $product,
                $destination ?? $this->quickAddDestination,
            );

            (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
                'name' => $product->name,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => (string) max(0.01, $quantity),
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'parent_path' => $parentPath,
            ]));

            $this->refreshOpportunity();
        }, 'Item added');
    }

    public function quickAdd(int $productId, float $quantity = 1): void
    {
        $this->addProduct($productId, $quantity, $this->quickAddDestination);
    }

    public function addGroup(?int $parentGroupId = null, string $name = 'New section'): int
    {
        $this->guardEditable();

        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $beforeIds = $opportunity->items->pluck('id');
        $parentPath = $parentGroupId === null
            ? null
            : $this->treeService()->parentPathForGroupId($opportunity->items, $parentGroupId);

        (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
            'name' => $name,
            'parent_path' => $parentPath,
        ]));

        $this->refreshOpportunity();

        $fresh = $this->opportunity->fresh(['items']) ?? $this->opportunity;

        $newGroup = $fresh->items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group
                && ! $beforeIds->contains($item->id))
            ->sortByDesc('id')
            ->first();

        return (int) ($newGroup?->id ?? 0);
    }

    public function addAccessory(int $principalItemId, int $productId, float $quantity = 1): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($principalItemId, $productId, $quantity): void {
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The selected product could not be found.',
                ]);
            }

            (new AddOpportunityAccessory)($this->opportunity->fresh(['items']) ?? $this->opportunity, AddOpportunityAccessoryData::from([
                'name' => $product->name,
                'principal_item_id' => $principalItemId,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => (string) max(0, $quantity),
            ]));

            $this->refreshOpportunity();
        }, 'Accessory added');
    }

    public function renameItem(int $itemId, string $name): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $name): void {
            $item = $this->findItem($itemId);
            (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => trim($name)]));

            $this->refreshOpportunity();
        }, 'Renamed');
    }

    public function updateField(int $id, string $field, mixed $value): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($id, $field, $value): void {
            $item = $this->findItem($id);

            match ($field) {
                'quantity' => (new ChangeItemQuantity)($item, ChangeItemQuantityData::from([
                    'quantity' => (string) $value,
                ])),
                'unit_price' => (new OverrideItemPrice)($item, OverrideItemPriceData::from([
                    'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                    'unit_price' => $value === null || $value === '' ? null : (string) $value,
                ])),
                'discount_percent' => (new SetItemDiscount)($item, SetItemDiscountData::from([
                    'discount_percent' => $value === null || $value === '' ? null : (string) $value,
                ])),
                'name' => (new RenameOpportunityItem)($item, RenameOpportunityItemData::from([
                    'name' => (string) $value,
                ])),
                'starts_at' => (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => $value === '' ? null : $value,
                    'ends_at' => optional($item->ends_at)?->toDateString(),
                ])),
                'ends_at' => (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => optional($item->starts_at)?->toDateString(),
                    'ends_at' => $value === '' ? null : $value,
                ])),
                'dates' => (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => is_array($value) ? ($value['starts_at'] ?? null) : null,
                    'ends_at' => is_array($value) ? ($value['ends_at'] ?? null) : null,
                ])),
                default => throw ValidationException::withMessages([
                    'field' => "Unknown field: {$field}",
                ]),
            };

            $this->refreshOpportunity();
        });
    }

    public function toggleOptional(int $itemId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId): void {
            $item = $this->findItem($itemId);
            $nowOptional = ! $item->is_optional;

            (new ToggleItemOptional)($item, ToggleItemOptionalData::from([
                'is_optional' => $nowOptional,
            ]));

            $this->refreshOpportunity();

            $this->dispatch('toast', type: 'success', message: $nowOptional ? 'Marked optional' : 'Marked required');
        });
    }

    public function substituteItem(int $itemId, int $productId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $productId): void {
            $item = $this->findItem($itemId);
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The replacement product could not be found.',
                ]);
            }

            (new SubstituteItem)($item, SubstituteItemData::from([
                'item_id' => $product->id,
                'itemable_type' => Product::class,
                'name' => $product->name,
            ]));

            $this->refreshOpportunity();
        }, 'Product substituted');
    }

    public function removeItem(int $itemId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId): void {
            (new RemoveOpportunityItem)($this->findItem($itemId));

            $this->refreshOpportunity();
        }, 'Item removed');
    }

    /**
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array{stale: bool, revision: int}
     */
    public function persistTree(array $nodes, int $baseRevision = 0): array
    {
        $this->guardEditable();

        $reconciler = app(LineItemTreeReconciler::class);
        $serverRevision = $this->treeRevision();

        if ($reconciler->isStale($baseRevision, $serverRevision)) {
            return ['stale' => true, 'revision' => $serverRevision];
        }

        (new RestructureOpportunityItems)(
            $this->opportunity->fresh(['items']) ?? $this->opportunity,
            RestructureOpportunityItemsData::from(['nodes' => $nodes]),
        );

        $this->refreshOpportunity();

        return ['stale' => false, 'revision' => $this->treeRevision()];
    }

    public function mergeDuplicates(int $survivorId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($survivorId): void {
            $survivor = $this->findItem($survivorId);
            $duplicateIds = $this->duplicateIdsFor($survivor);

            if ($duplicateIds === []) {
                return;
            }

            (new MergeOpportunityItems)($survivor, MergeOpportunityItemsData::from([
                'duplicate_item_ids' => $duplicateIds,
            ]));

            $this->refreshOpportunity();
        }, 'Duplicates merged');
    }

    public function assignToGroup(int $itemId, ?int $groupId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $groupId): void {
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $destination = $groupId === null ? null : 'group:'.$groupId;
            $nodes = $tree->nodesAfterMovingLine($opportunity->items, $itemId, 0, $destination);
            $tree->restructure($opportunity, $nodes);

            $this->refreshOpportunity();

            $label = $groupId === null
                ? 'auto group'
                : $this->findGroup($groupId)->name;

            $this->dispatch('toast', type: 'success', message: 'Moved to '.$label);
        });
    }

    public function setDealPrice(string $dealTotal): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($dealTotal): void {
            (new SetDealPrice)($this->opportunity, SetDealPriceData::from([
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'deal_total' => $dealTotal,
            ]));

            $this->refreshOpportunity();
        }, 'Deal price set');
    }

    public function clearDealPrice(): void
    {
        $this->guardEditable();

        $this->runMutation(function (): void {
            (new ClearDealPrice)($this->opportunity);

            $this->refreshOpportunity();
        }, 'Deal price cleared');
    }

    public function updateQuantity(int $itemId, string $quantity): void
    {
        $this->updateField($itemId, 'quantity', $quantity);
    }

    public function overridePrice(int $itemId, ?string $unitPrice): void
    {
        $this->updateField($itemId, 'unit_price', $unitPrice);
    }

    public function setDiscount(int $itemId, ?string $discountPercent): void
    {
        $this->updateField($itemId, 'discount_percent', $discountPercent);
    }

    public function changeDates(int $itemId, ?string $startsAt, ?string $endsAt): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $startsAt, $endsAt): void {
            $item = $this->findItem($itemId);
            (new ChangeItemDates)($item, ChangeItemDatesData::from([
                'starts_at' => $startsAt === '' ? null : $startsAt,
                'ends_at' => $endsAt === '' ? null : $endsAt,
            ]));

            $this->refreshOpportunity();
        }, 'Dates updated');
    }

    public function assignToSection(int $itemId, ?int $groupId): void
    {
        $this->assignToGroup($itemId, $groupId);
    }

    public function handleSort(int $itemId, int $position, ?string $groupId = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $position, $groupId): void {
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $nodes = $tree->nodesAfterMovingLine(
                $opportunity->items,
                $itemId,
                $position,
                $this->normalizeGroupKey($groupId),
            );
            $tree->restructure($opportunity, $nodes);

            $this->refreshOpportunity();
        });
    }

    public function handleSectionSort(int $groupId, int $position, ?string $parentGroupKey = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($groupId, $position, $parentGroupKey): void {
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $nodes = $tree->nodesAfterMovingGroup(
                $opportunity->items,
                $groupId,
                $position,
                $this->normalizeParentGroupKey($parentGroupKey),
            );
            $tree->restructure($opportunity, $nodes);

            $this->refreshOpportunity();
        });
    }

    /**
     * @param  array<int, int|string>  $groupIds
     */
    public function reorderSections(array $groupIds): void
    {
        $this->guardEditable();

        $groupIds = array_values(array_map('intval', $groupIds));

        if ($groupIds === []) {
            return;
        }

        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $items = $opportunity->items;
        $tree = $this->treeService();
        $first = $items->firstWhere('id', $groupIds[0]);

        if ($first === null || $first->item_type !== OpportunityItemType::Group) {
            return;
        }

        $parentGroupKey = $this->parentGroupKeyForPath($items, $first->parentPath());

        foreach ($groupIds as $position => $groupId) {
            $nodes = $tree->nodesAfterMovingGroup($items, $groupId, $position, $parentGroupKey);
            $tree->restructure($opportunity, $nodes);
            $opportunity = $opportunity->fresh(['items']) ?? $opportunity;
            $items = $opportunity->items;
        }

        $this->refreshOpportunity();
    }

    public function renameSection(int $groupId, string $name): void
    {
        $this->renameItem($groupId, $name);
    }

    public function deleteSection(int $groupId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($groupId): void {
            $group = $this->findGroup($groupId);
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $nodes = $tree->nodesAfterDissolvingGroup($opportunity->items, $groupId);
            $tree->restructure($opportunity, $nodes);
            (new RemoveOpportunityItem)($group->fresh());

            $this->refreshOpportunity();
        }, 'Section deleted');
    }

    /**
     * @return array<int, bool>
     */
    #[Computed]
    public function duplicateLineIds(): array
    {
        $items = $this->opportunity->items()->get();
        $byKey = [];

        foreach ($items as $item) {
            if ($item->itemable_id === null) {
                continue;
            }

            $key = implode('|', [
                $item->itemable_id,
                $item->itemable_type,
                $item->getRawOriginal('transaction_type'),
                $item->getRawOriginal('charge_period'),
                $item->is_optional ? 1 : 0,
                $item->parentPath() ?? 'null',
                optional($item->starts_at)->toIso8601String() ?? 'null',
                optional($item->ends_at)->toIso8601String() ?? 'null',
            ]);

            $byKey[$key][] = (int) $item->id;
        }

        $flagged = [];

        foreach ($byKey as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $flagged[$id] = true;
                }
            }
        }

        return $flagged;
    }

    public function createSection(): void
    {
        $this->guardEditable();

        $name = trim($this->newSectionName);

        if ($name === '') {
            return;
        }

        $parentId = $this->newSectionParent === '' ? null : (int) $this->newSectionParent;

        try {
            $this->addGroup($parentId, $name);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            $this->dispatch('toast', type: 'error', message: $this->firstMessageFrom($e));

            return;
        }

        $this->newSectionName = '';
        $this->newSectionParent = '';
        $this->dispatch('close-modal', 'create-section');
        $this->dispatch('toast', type: 'success', message: 'Section created');
    }

    /**
     * @return array{tree: array<int, array<string, mixed>>, revision: int}
     */
    public function serverTree(): array
    {
        unset($this->tree);

        return [
            'tree' => $this->tree(),
            'revision' => $this->treeRevision(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  array<int, int>  $pendingLocalIds
     * @return array{stale: bool, revision: int, tree: array<int, array<string, mixed>>, conflicts: array<int, string>}
     */
    public function pullTree(int $baseRevision, array $localRows = [], array $pendingLocalIds = []): array
    {
        unset($this->tree);

        $reconciler = app(LineItemTreeReconciler::class);
        $serverRevision = $this->treeRevision();
        $serverTree = $this->tree();
        $stale = $reconciler->isStale($baseRevision, $serverRevision);

        $tree = $serverTree;
        $conflicts = [];

        if ($localRows !== [] && ($stale || $pendingLocalIds !== [])) {
            $result = $reconciler->reconcile($localRows, $serverTree, $pendingLocalIds);
            $tree = $result['rows'];
            $conflicts = $result['conflicts'];
        }

        return [
            'stale' => $stale,
            'revision' => $serverRevision,
            'tree' => $tree,
            'conflicts' => $conflicts,
        ];
    }

    private function normalizeParentGroupKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        if (str_starts_with($key, 'section-parent:')) {
            return 'group-parent:'.substr($key, strlen('section-parent:'));
        }

        return $key;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OpportunityItem>  $items
     */
    private function parentGroupKeyForPath(\Illuminate\Support\Collection $items, ?string $parentPath): string
    {
        if ($parentPath === null || $parentPath === '') {
            return 'group-parent:root';
        }

        $parent = $items->first(
            fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group
                && $item->path === $parentPath,
        );

        return $parent !== null ? 'group-parent:'.$parent->id : 'group-parent:root';
    }

    private function treeService(): OpportunityEditorTreeService
    {
        return app(OpportunityEditorTreeService::class);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OpportunityItem>  $items
     */
    private function resolveParentPathForAdd(
        Opportunity $opportunity,
        \Illuminate\Support\Collection $items,
        Product $product,
        ?string $destination,
    ): ?string {
        $tree = $this->treeService();
        $normalized = $this->normalizeGroupKey($destination);

        if ($normalized !== null && $normalized !== '' && $normalized !== 'auto:ungrouped') {
            return $tree->parentPathForGroupKey($items, $normalized);
        }

        if ($normalized === 'auto:ungrouped') {
            return null;
        }

        $stub = new OpportunityItem([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
        ]);

        return $tree->findOrCreateAutoGroup($opportunity, $stub, $this->productCache())->path;
    }

    private function normalizeGroupKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (str_starts_with($key, 'section:')) {
            return 'group:'.substr($key, strlen('section:'));
        }

        return $key;
    }

    /** @return array<int, int> */
    private function duplicateIdsFor(OpportunityItem $survivor): array
    {
        return OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKeyNot($survivor->id)
            ->where('itemable_id', $survivor->itemable_id)
            ->where('itemable_type', $survivor->itemable_type)
            ->where('transaction_type', $survivor->getRawOriginal('transaction_type'))
            ->where('charge_period', $survivor->getRawOriginal('charge_period'))
            ->where('is_optional', $survivor->is_optional)
            ->when(
                $survivor->parentPath() === null,
                fn ($q) => $q->whereRaw('length(path) = 4'),
                fn ($q) => $q->where('path', 'like', $survivor->parentPath().'%')
                    ->whereRaw('length(path) = ?', [strlen((string) $survivor->parentPath()) + 4]),
            )
            ->where('starts_at', $survivor->starts_at)
            ->where('ends_at', $survivor->ends_at)
            ->whereNotNull('itemable_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, Product> */
    private function productCache(): array
    {
        return once(function (): array {
            $productIds = $this->opportunity->items()
                ->whereNotNull('itemable_id')
                ->pluck('itemable_id')
                ->unique()
                ->all();

            if ($productIds === []) {
                return [];
            }

            return Product::query()
                ->whereIn('id', $productIds)
                ->with(['productGroup.parent', 'accessories.accessoryProduct'])
                ->get()
                ->keyBy('id')
                ->all();
        });
    }

    private function findItem(int $itemId): OpportunityItem
    {
        $item = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($itemId)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'item' => 'The line item could not be found.',
            ]);
        }

        return $item;
    }

    private function findGroup(int $groupId): OpportunityItem
    {
        $group = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($groupId)
            ->first();

        if ($group === null || $group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'The group could not be found.',
            ]);
        }

        return $group;
    }

    private function guardEditable(): void
    {
        Gate::authorize('opportunities.edit');

        if ($this->opportunity->statusEnum()->isClosed()) {
            throw ValidationException::withMessages([
                'opportunity' => 'This opportunity is closed and its line items cannot be edited.',
            ]);
        }
    }

    private function refreshOpportunity(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;

        unset($this->tree, $this->destinations, $this->sectionOptions, $this->parentGroupOptions, $this->duplicateLineIds);

        $this->dispatch('opportunity-totals-updated');
    }

    private function runMutation(callable $callback, ?string $successMessage = null): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $this->firstMessageFrom($e));

            throw $e;
        }

        if ($successMessage !== null) {
            $this->dispatch('toast', type: 'success', message: $successMessage);
        }
    }

    private function firstMessageFrom(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            foreach ((array) $messages as $message) {
                return (string) $message;
            }
        }

        return $e->getMessage();
    }
}; ?>

@php
    $opp = $this->opportunity;
    $formatter = app(\App\Support\Formatter::class);
    $grandTotal = $formatter->money($opp->charge_total ?? 0);
    $currencySymbol = trim(preg_replace('/[0-9.,\s]/u', '', $formatter->money(0, $opp->currency_code ?? settings('company.base_currency', 'GBP'))));
    $dealTotalRaw = $opp->deal_total !== null ? $opp->formatMoneyCost('deal_total') : '';
    $echoChannel = 'availability.opportunity.'.$opp->id;
@endphp

<div
    class="opportunity-line-items-editor w-full"
    x-data="window.signals.lineItemsEditor({
        oppId: {{ $opp->id }},
        editable: @js($editable),
        catalogue: @js($catalogue),
        currencySymbol: @js($currencySymbol),
        echoChannel: @js($echoChannel),
        destinations: @js($this->destinations),
        sectionOptions: @js($this->sectionOptions),
        serverGrandTotal: @js($opp->formatMoneyCost('charge_total')),
        dealTotalRaw: @js($dealTotalRaw),
        hasDealPrice: @js($opp->deal_total !== null),
    })"
    x-init="boot()"
    data-server-grand-total="{{ $opp->formatMoneyCost('charge_total') }}"
>
    {{-- Frozen seed island — first write wins; never re-emitted into x-data --}}
    <script wire:ignore data-lf-seed>
        (function () {
            window.__lfSeed = window.__lfSeed || {};
            var key = {{ $opp->id }};
            if (!(key in window.__lfSeed)) {
                window.__lfSeed[key] = {
                    tree: @js($this->tree),
                    revision: @js($this->treeRevision()),
                };
            }
        })();
    </script>

    {{-- Toolbar: sync pills, quick-add, section actions --}}
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <span
            class="s-badge"
            :class="{
                's-badge-emerald': syncState === 'synced',
                's-badge-amber': syncState === 'syncing',
                's-badge-blue': syncState === 'cached',
                's-badge-zinc': syncState === 'idle',
            }"
            title="Local-first sync status"
        >
            <span class="s-badge-dot" :class="{ 'animate-pulse': syncState === 'syncing' }"></span>
            <span x-text="syncLabel"></span>
            <span x-show="queue.length" x-cloak class="ml-1 opacity-70" x-text="'(' + queue.length + ')'"></span>
        </span>

        <span
            x-show="conflictCount > 0"
            x-cloak
            class="s-badge s-badge-red"
            title="Some lines were changed elsewhere"
        >
            <span class="s-badge-dot"></span>
            <span x-text="conflictCount + ' conflict' + (conflictCount === 1 ? '' : 's')"></span>
        </span>

        @if($editable)
            <div class="flex items-center gap-2 flex-1 min-w-[320px] s-panel" style="padding: 8px 10px;">
                <span class="text-[var(--accent)] font-bold">+</span>
                <div class="relative flex-1" x-data="{ q: '' }">
                    <input
                        type="text"
                        class="s-input w-full"
                        placeholder="Quick add — &ldquo;6 spiider&rdquo;, or a SKU, then Enter"
                        autocomplete="off"
                        x-model="q"
                        x-ref="quickAddInput"
                        x-on:input="onPickerInput($refs.quickAddInput, q, true)"
                        x-on:keydown="onPickerKeydown($event, $refs.quickAddInput, true)"
                        x-on:focus="onPickerInput($refs.quickAddInput, q, true)"
                        x-on:blur="closePickerSoon()"
                    >
                    <span class="text-xs text-[var(--text-faint)] font-mono" x-show="quickAddQtyHint" x-text="quickAddQtyHint"></span>
                </div>
                <label class="text-xs text-[var(--text-faint)]">qty</label>
                <input type="number" min="1" step="1" class="s-input text-center font-mono" style="width: 64px;" x-model.number="quickAddQty">
                <span class="text-xs text-[var(--text-faint)]">into</span>
                <select class="s-input" style="max-width: 230px;" wire:model="quickAddDestination">
                    @foreach($this->destinations as $dest)
                        <option value="{{ $dest['value'] }}" wire:key="dest-{{ $dest['value'] }}">{{ $dest['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" class="s-btn s-btn-ghost" x-on:click="expandAll()">Expand all</button>
            <button type="button" class="s-btn s-btn-ghost" x-on:click="collapseAll()">Collapse all</button>
            <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('open-modal', 'create-section')">+ Section</button>
        @endif

        <span class="ml-auto text-sm text-[var(--text-muted)]">
            Charge total:
            <strong class="text-[var(--text)] tabular-nums" x-text="displayGrandTotal"></strong>
        </span>
    </div>

    @if($editable)
        <div class="flex flex-wrap items-center gap-2 mb-3 s-panel" style="padding: 8px 12px;">
            <span class="text-sm font-medium">Deal price</span>
            <input type="text" class="s-input font-mono" style="width: 120px;" x-model="dealPriceInput" placeholder="e.g. 750.00">
            <button type="button" class="s-btn s-btn-sm" x-on:click="applyDealPrice()">Set</button>
            <button type="button" class="s-btn s-btn-sm s-btn-ghost" x-show="hasDealPrice" x-on:click="clearDealPrice()">Clear</button>
        </div>
    @elseif($opp->deal_total !== null)
        <p class="text-sm text-[var(--text-muted)] mb-3">
            Deal price: <strong class="font-mono">{{ $formatter->money($opp->deal_total, $opp->currency_code ?? settings('company.base_currency', 'GBP')) }}</strong>
        </p>
    @endif

    {{-- Alpine-owned table — Livewire must not morph this subtree --}}
    <x-signals.card>
        <div class="overflow-x-auto" wire:ignore>
            <table class="s-table lf-table w-full text-sm select-none">
                <thead>
                    <tr>
                        <th class="text-left" style="min-width: 320px;">Product</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Qty</th>
                        <th class="text-left">Days</th>
                        <th class="text-left">Price</th>
                        <th class="text-right" style="text-align: right;">Disc %</th>
                        <th class="text-right" style="text-align: right; min-width: 150px; white-space: nowrap;">Charge Total</th>
                        <th class="text-right" style="width: 48px;"></th>
                    </tr>
                </thead>
                <tbody x-ref="tbody">
                    <template x-for="row in visibleRows" :key="row.id">
                        <tr
                            class="lf-row"
                            :class="{
                                'lf-row-group': row.item_type === 'group',
                                'lf-row-dragging': dragId === row.id,
                                'opacity-60': row.is_optional,
                            }"
                            :data-id="row.id"
                        >
                            <td>
                                <div class="flex items-center gap-1" :style="`padding-left:${(row.depth - 1) * 22}px`">
                                    <span
                                        x-show="editable"
                                        class="lf-handle"
                                        title="Drag to move / re-nest"
                                        @pointerdown="onHandleDown($event, row.id)"
                                    >☰</span>
                                    <button
                                        type="button"
                                        class="lf-caret"
                                        x-show="row.has_children"
                                        @click="toggleCollapse(row.id)"
                                        x-text="row.is_collapsed ? '▸' : '▾'"
                                    ></button>
                                    <span x-show="!row.has_children" class="lf-caret-spacer"></span>
                                    <template x-if="row.item_type === 'accessory'">
                                        <span class="text-[var(--text-muted)]">↳</span>
                                    </template>
                                    <span
                                        class="lf-name"
                                        :class="{ 'font-semibold': row.item_type === 'group' }"
                                        @dblclick="editable && beginEdit(row.id, 'name', $event)"
                                        x-text="row.name"
                                    ></span>
                                    <template x-if="row.item_type === 'group'">
                                        <span class="s-badge s-badge-blue ml-1">section</span>
                                    </template>
                                    <template x-if="row.is_optional">
                                        <span class="s-badge s-badge-zinc ml-1">Optional</span>
                                    </template>
                                </div>
                            </td>
                            <td>
                                <span x-show="row.item_type !== 'group'" class="s-chip" x-text="row.type_label || row.charge_period_label || '—'"></span>
                            </td>
                            <td>
                                <template x-if="row.item_type !== 'group' && row.status_label">
                                    <span class="s-badge" :class="statusClass(row.status_label)" x-text="row.status_label"></span>
                                </template>
                            </td>
                            <td class="text-left tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="quantity" @click="editable && beginEdit(row.id, 'quantity', $event)" x-text="fmtQty(row.quantity)"></span>
                            </td>
                            <td class="text-left tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="days" @click="editable && beginEdit(row.id, 'days', $event)" x-text="row.days"></span>
                            </td>
                            <td class="text-left tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left lf-cell-price" data-field="unit_price" @click="editable && beginEdit(row.id, 'unit_price', $event)" x-text="row.unit_price_display"></span>
                            </td>
                            <td class="text-right tabular-nums">
                                <span x-show="row.item_type !== 'group'" class="lf-cell" data-field="discount_percent" @click="editable && beginEdit(row.id, 'discount_percent', $event)" x-text="(row.discount_percent ? row.discount_percent : 0) + '%'"></span>
                            </td>
                            <td class="text-right tabular-nums font-medium" style="white-space: nowrap;">
                                <span x-show="row.item_type === 'group'" class="text-[var(--text-muted)]" x-text="groupSubtotalDisplay(row)"></span>
                                <span x-show="row.item_type !== 'group'" x-text="row.charge_total_display"></span>
                            </td>
                            <td class="text-right">
                                <div class="relative inline-block" @click.outside="openMenu === row.id && (openMenu = null, confirmDeleteId = null)">
                                    <button type="button" class="lf-menu-btn" @click.stop="openMenu = (openMenu === row.id ? null : row.id); confirmDeleteId = null">▾</button>
                                    <div x-show="openMenu === row.id" x-cloak x-transition.opacity class="lf-menu">
                                        <template x-if="editable && row.item_type === 'group'">
                                            <button type="button" @click="$dispatch('open-modal', { id: 'rename-section', itemId: row.id, name: row.name }); openMenu = null">Rename section</button>
                                        </template>
                                        <template x-if="editable && row.item_type !== 'group'">
                                            <button type="button" @click="openEditLineModal(row); openMenu = null">Edit line…</button>
                                        </template>
                                        <template x-if="row.availability_url">
                                            <a :href="row.availability_url" target="_blank" rel="noopener" class="block px-2.5 py-1.5 text-sm hover:bg-[var(--surface-2)] rounded" @click="openMenu = null">View availability</a>
                                        </template>
                                        <template x-if="editable && row.item_type !== 'group'">
                                            <button type="button" @click="toggleOptionalRow(row); openMenu = null" x-text="row.is_optional ? 'Mark required' : 'Mark optional'"></button>
                                        </template>
                                        <template x-if="editable && row.has_duplicates">
                                            <button type="button" @click="mergeDupes(row); openMenu = null">Merge duplicates</button>
                                        </template>
                                        <template x-if="editable">
                                            <hr class="my-1 border-[var(--border)]">
                                            <template x-for="g in groupMoveOptions(row)" :key="g.id">
                                                <button type="button" @click="assignRowToGroup(row, g.id); openMenu = null" x-text="'Move to ' + g.name"></button>
                                            </template>
                                        </template>
                                        <template x-if="editable">
                                            <button type="button" class="lf-menu-danger" :class="{ 'lf-menu-delete-armed': confirmDeleteId === row.id }"
                                                @click.stop="confirmDeleteId === row.id ? (deleteNode(row.id), openMenu = null, confirmDeleteId = null) : (confirmDeleteId = row.id)"
                                                x-text="confirmDeleteId === row.id ? 'Click again to confirm' : 'Remove'"
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!rows.length">
                        <td colspan="9" class="text-center text-[var(--text-muted)] py-4">
                            {{ $editable ? 'No line items yet. Use quick-add or add a section to start.' : 'This opportunity has no line items yet.' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot x-show="rows.length">
                    <tr class="s-table-total-row" style="border-top: 2px solid var(--card-border);">
                        <td colspan="7" class="text-right font-semibold">Charge total (ex-tax)</td>
                        <td class="text-right font-mono font-semibold text-lg" x-text="displayGrandTotal"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-signals.card>

    <div x-ref="ghost" class="lf-ghost" x-show="dragId" x-cloak></div>

    {{-- Product picker dropdown --}}
    <template x-teleport="body">
        <div x-ref="pickerDropdown" x-show="picker.open" x-cloak class="s-dropdown"
            style="position: absolute; z-index: 120; min-width: 300px; max-height: 340px; overflow-y: auto;"
            x-on:mousedown.prevent>
            <template x-for="(hit, i) in picker.results" :key="hit.id">
                <button type="button" class="s-dropdown-item flex items-center gap-2 w-full text-left"
                    :style="i === picker.highlight ? 'background: var(--s-subtle);' : ''"
                    x-on:mousedown.prevent="choosePickerHit(hit)"
                    x-on:mouseenter="picker.highlight = i">
                    <span class="flex-1 truncate" x-text="hit.name"></span>
                    <span class="font-mono text-xs text-[var(--text-faint)]" x-text="hit.sku || ''"></span>
                </button>
            </template>
        </div>
    </template>

    {{-- Create section modal --}}
    <x-signals.modal name="create-section" title="New section" id="create-section-modal">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium mb-1">Section name</label>
                <input type="text" class="s-input w-full" wire:model="newSectionName" wire:keydown.enter.prevent="createSection" placeholder="e.g. Front of House">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nest under (optional)</label>
                <select class="s-input w-full" wire:model="newSectionParent">
                    @foreach($this->sectionOptions as $option)
                        <option value="{{ $option['value'] }}" wire:key="parent-opt-{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'create-section')">Cancel</button>
                <button type="button" class="s-btn s-btn-primary" wire:click="createSection">Create</button>
            </x-slot:footer>
        </div>
    </x-signals.modal>

    {{-- Rename section modal --}}
    <div x-data="{ open: false, itemId: null, name: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'rename-section') { open = true; itemId = $event.detail.itemId; name = $event.detail.name; }">
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-on:click.self="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Rename section</span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body">
                        <input type="text" class="s-input w-full" x-model="name">
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="if (name.trim()) { $wire.renameItem(itemId, name.trim()); open = false; }">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Edit line modal --}}
    <div x-data="{ open: false, line: null, unitPrice: '', discount: '', startsAt: '', endsAt: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'edit-line') {
            open = true; line = $event.detail.line;
            unitPrice = line.unit_price_raw ?? '';
            discount = line.discount_percent ?? '';
            startsAt = line.starts_at ?? '';
            endsAt = line.ends_at ?? '';
        }">
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-on:click.self="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Edit line — <span x-text="line?.name"></span></span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Substitute product</label>
                            <input type="text" class="s-input w-full" placeholder="Search catalogue…" autocomplete="off"
                                x-ref="substituteInput"
                                x-on:input="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:keydown="onPickerKeydown($event, $refs.substituteInput, false)"
                                x-on:focus="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:blur="closePickerSoon()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Unit price override</label>
                            <input type="text" class="s-input w-full font-mono" x-model="unitPrice">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Discount %</label>
                            <input type="number" min="0" max="100" step="0.01" class="s-input w-full font-mono" x-model="discount">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">Starts</label>
                                <input type="date" class="s-input w-full" x-model="startsAt">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Ends</label>
                                <input type="date" class="s-input w-full" x-model="endsAt">
                            </div>
                        </div>
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="
                                $wire.updateField(line.id, 'unit_price', unitPrice === '' ? null : unitPrice);
                                $wire.updateField(line.id, 'discount_percent', discount === '' ? null : discount);
                                $wire.updateField(line.id, 'dates', { starts_at: startsAt || null, ends_at: endsAt || null });
                                open = false;
                            ">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

@push('scripts')
    <script
        src="https://unpkg.com/dexie@4.0.11/dist/dexie.min.js"
        integrity="sha384-cPcqy69aXDd/DVAhVjR7F1pRGPSPFhhPA108OQ8DcgTdZixJZc3W4gzurSW1WfgH"
        crossorigin="anonymous"
    ></script>
    <style>
        [x-cloak] { display: none !important; }
        .lf-row-dragging { opacity: .35; }
        .lf-handle { cursor: grab; color: var(--text-muted); padding: 0 4px; font-size: 13px; touch-action: none; user-select: none; }
        .lf-handle:active { cursor: grabbing; }
        .lf-caret { width: 16px; height: 16px; font-size: 11px; color: var(--text-muted); background: none; border: 0; cursor: pointer; }
        .lf-caret-spacer { display: inline-block; width: 16px; }
        .lf-ghost {
            position: fixed; top: 0; left: 0; z-index: 9999; pointer-events: none;
            padding: 4px 12px; background: var(--surface, #fff);
            border: 1px solid var(--brand-primary, #1e3a5f); border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25); font-size: 13px; max-width: 360px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: .95;
        }
        .lf-placeholder td { height: 0; padding: 0; border: 0; }
        .lf-placeholder .lf-placeholder-bar {
            height: 3px; background: var(--brand-primary, #1e3a5f); border-radius: 2px; margin: 1px 0;
        }
        .lf-placeholder-invalid .lf-placeholder-bar {
            background: repeating-linear-gradient(90deg, var(--danger, #dc2626) 0, var(--danger, #dc2626) 6px, transparent 6px, transparent 12px);
            opacity: .8;
        }
        .lf-menu {
            position: absolute; right: 0; top: 100%; z-index: 40; min-width: 180px; margin-top: 4px;
            background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,.18); padding: 4px; text-align: left;
        }
        .lf-menu button, .lf-menu a { display: block; width: 100%; text-align: left; padding: 6px 10px; border: 0; background: none; cursor: pointer; font-size: 13px; color: var(--text); border-radius: 4px; }
        .lf-menu button:hover, .lf-menu a:hover { background: var(--surface-2, rgba(127,127,127,.1)); }
        .lf-menu-danger { color: var(--danger, #dc2626) !important; }
        .lf-menu-delete-armed { background: var(--danger, #dc2626) !important; color: #fff !important; font-weight: 600; }
        .lf-menu-btn { border: 0; background: none; cursor: pointer; color: var(--text-muted); padding: 2px 6px; border-radius: 4px; }
        .lf-name { cursor: text; }
        .lf-cell { cursor: pointer; padding: 2px 5px; border-radius: 4px; border: 1px solid transparent; display: inline-block; box-sizing: border-box; width: 78px; text-align: right; }
        .lf-cell:hover { border-color: var(--border); background: var(--surface-2, rgba(127,127,127,.08)); }
        .lf-cell-left { text-align: left; }
        .lf-cell-price { width: 135px; }
        .lf-edit-input { width: 78px; padding: 2px 5px; box-sizing: border-box; border: 1px solid var(--link, #2563eb); border-radius: 4px; background: var(--surface, #fff); color: var(--text); text-align: right; font: inherit; }
        .lf-edit-input.lf-edit-text { width: 220px; text-align: left; }
        .lf-edit-input.lf-edit-price { width: 135px; text-align: left; }
    </style>
@endpush
