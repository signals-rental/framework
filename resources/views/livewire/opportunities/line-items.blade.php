<?php

use App\Contracts\Opportunities\OpportunityLineItemsEditorContract;
use App\Actions\Opportunities\AddOpportunityAccessory;
use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\MergeOpportunityItems;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Actions\Opportunities\UpdateOpportunityItemDetails;
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
use App\Data\Opportunities\UpdateOpportunityItemDetailsData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Opportunities\LineItemTreeReconciler;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use App\Services\Opportunities\OpportunityLineItemTreeRevision;
use App\Services\Opportunities\ProductSearchService;
use Illuminate\Support\Carbon;
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

    public bool $fieldsEditable = false;

    public string $newSectionName = '';

    public string $newSectionParent = '';

    public string $newTextLineName = '';

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->editable = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();
        $this->fieldsEditable = $this->editable && ! $opportunity->pricingFrozen();
        $this->catalogue = app(ProductSearchService::class)->catalogueIndex($opportunity->store_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function tree(): array
    {
        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;

        return app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);
    }

    public function treeRevision(): int
    {
        return app(OpportunityLineItemTreeRevision::class)->current($this->opportunity->id);
    }

    #[Renderless]
    public function treeRevisionToken(): string
    {
        return (string) $this->treeRevision();
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

    #[Renderless]
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

            $itemPayload = [
                'name' => $product->name,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => (string) max(0.01, $quantity),
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'parent_path' => $parentPath,
            ];

            if ($this->opportunity->pricingFrozen()) {
                $itemPayload['unit_price'] = 0;
            }

            (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($itemPayload));

            $this->refreshOpportunity();
        }, 'Item added');
    }

    #[Renderless]
    public function quickAdd(int $productId, float $quantity = 1): void
    {
        $this->addProduct($productId, $quantity, $this->quickAddDestination);
    }

    public function addTextLine(): void
    {
        $this->guardEditable();

        $name = trim($this->newTextLineName);

        if ($name === '') {
            $this->addError('newTextLineName', 'Enter a name for the free text item.');

            return;
        }

        try {
            $this->runMutation(function () use ($name): void {
                $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;

                $payload = [
                    'name' => $name,
                    'item_type' => OpportunityItemType::Text->value,
                    'quantity' => '1',
                    'parent_path' => $this->resolveParentPathForText(
                        $opportunity->items,
                        $this->quickAddDestination,
                    ),
                ];

                if ($this->opportunity->pricingFrozen()) {
                    $payload['unit_price'] = 0;
                    $payload['currency'] = $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP');
                }

                (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($payload));

                $this->refreshOpportunity();
            }, 'Free text item added', syncTree: true, mutationMeta: ['modal' => 'add-text-line']);

            $this->newTextLineName = '';
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    #[Renderless]
    public function addInlineTextLine(): int
    {
        $this->guardEditable();

        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $beforeIds = $opportunity->items->pluck('id');

        $payload = [
            'name' => '',
            'item_type' => OpportunityItemType::Text->value,
            'quantity' => '1',
            'parent_path' => $this->resolveParentPathForText(
                $opportunity->items,
                $this->quickAddDestination,
            ),
        ];

        if ($this->opportunity->pricingFrozen()) {
            $payload['unit_price'] = 0;
            $payload['currency'] = $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP');
        }

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($payload));

        $this->refreshOpportunity();

        $fresh = $this->opportunity->fresh(['items']) ?? $this->opportunity;

        $newItem = $fresh->items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Text
                && ! $beforeIds->contains($item->id))
            ->sortByDesc('id')
            ->first();

        return (int) ($newItem?->id ?? 0);
    }

    #[Renderless]
    public function addInlineSection(): int
    {
        return $this->addGroup(null, '');
    }

    #[Renderless]
    public function addGroup(?int $parentGroupId = null, string $name = ''): int
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
        }, 'Renamed', syncTree: true, mutationMeta: ['modalId' => 'rename-section']);
    }

    public function saveLineEdits(
        int $itemId,
        ?string $unitPrice = null,
        ?string $discount = null,
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?string $description = null,
        ?string $notes = null,
    ): void {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $unitPrice, $discount, $startsAt, $endsAt, $description, $notes): void {
            $item = $this->findItem($itemId);

            if (! $this->opportunity->pricingFrozen()) {
                (new OverrideItemPrice)($item, OverrideItemPriceData::from([
                    'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                    'unit_price' => $unitPrice === null || $unitPrice === '' ? null : $unitPrice,
                ]));

                (new SetItemDiscount)($item, SetItemDiscountData::from([
                    'discount_percent' => $discount === null || $discount === '' ? null : $discount,
                ]));

                (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => $startsAt === '' ? null : $startsAt,
                    'ends_at' => $endsAt === '' ? null : $endsAt,
                ]));
            }

            (new UpdateOpportunityItemDetails)($item->fresh(), UpdateOpportunityItemDetailsData::from([
                'description' => $description === '' ? null : $description,
                'notes' => $notes === '' ? null : $notes,
            ]));

            $this->refreshOpportunity();
        }, 'Line updated', syncTree: true, mutationMeta: ['modalId' => 'edit-line']);
    }

    #[Renderless]
    public function updateField(int $id, string $field, mixed $value): void
    {
        $this->guardEditable();
        $this->guardFieldsEditable($field);

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
                'name' => $this->updateNameField($item, (string) $value),
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
                'days' => $this->changeItemDaysAndReturn($item, (int) $value),
                default => throw ValidationException::withMessages([
                    'field' => "Unknown field: {$field}",
                ]),
            };

            $this->refreshOpportunity();
        });
    }

    #[Renderless]
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
        }, syncTree: true);
    }

    #[Renderless]
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
        }, 'Product substituted', syncTree: true);
    }

    public function removeItem(int $itemId): void
    {
        $this->guardEditable();
        $this->guardPricingFrozenForRemoval();

        $this->runMutation(function () use ($itemId): void {
            (new RemoveOpportunityItem)($this->findItem($itemId));

            $this->refreshOpportunity();
        }, 'Item removed');
    }

    /**
     * Apply a deliberate editor tree restructure. The node list is authoritative
     * user intent — we always apply when validation passes. Omitted node ids are
     * removed server-side (`prune_orphans`) so local-first deletes persist through
     * this path. {@see $baseRevision} is advisory (revision drift detection / logging only).
     *
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array{stale: bool, revision: string, revision_drift: bool, base_revision: string, server_revision_before: string}
     */
    #[Renderless]
    public function persistTree(array $nodes, int $baseRevision = 0): array
    {
        $this->guardEditable();

        $serverRevision = $this->treeRevision();
        $revisionDrift = $baseRevision > 0 && $serverRevision > $baseRevision;

        (new RestructureOpportunityItems)(
            $this->opportunity->fresh(['items']) ?? $this->opportunity,
            RestructureOpportunityItemsData::from([
                'nodes' => $nodes,
                'prune_orphans' => true,
            ]),
        );

        $this->refreshOpportunity();

        return [
            'stale' => false,
            'revision' => (string) $this->treeRevision(),
            'revision_drift' => $revisionDrift,
            'base_revision' => (string) $baseRevision,
            'server_revision_before' => (string) $serverRevision,
        ];
    }

    #[Renderless]
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
        }, 'Duplicates merged', syncTree: true);
    }

    #[Renderless]
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
        }, syncTree: true);
    }

    #[Renderless]
    public function setDealPrice(string $dealTotal): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($dealTotal): void {
            (new SetDealPrice)($this->opportunity, SetDealPriceData::from([
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'deal_total' => $dealTotal,
            ]));

            $this->refreshOpportunity();
        }, 'Deal price set', syncTree: true);
    }

    #[Renderless]
    public function clearDealPrice(): void
    {
        $this->guardEditable();

        $this->runMutation(function (): void {
            (new ClearDealPrice)($this->opportunity);

            $this->refreshOpportunity();
        }, 'Deal price cleared', syncTree: true);
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
        $this->guardFieldsEditable('dates');

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
        $this->guardPricingFrozenForRemoval();

        $this->runMutation(function () use ($groupId): void {
            (new RemoveOpportunityItem)($this->findGroup($groupId));

            $this->refreshOpportunity();
        }, 'Section deleted', syncTree: true);
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
            $this->runMutation(function () use ($parentId, $name): void {
                $this->addGroup($parentId, $name);
            }, 'Section created', syncTree: true, mutationMeta: ['modal' => 'create-section']);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->newSectionName = '';
        $this->newSectionParent = '';
    }

    /**
     * @return array{charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    #[Renderless]
    public function totalsSnapshot(): array
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;

        return $this->totalsPayload();
    }

    #[Renderless]
    public function refreshEditorContext(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        unset($this->tree, $this->destinations, $this->sectionOptions, $this->parentGroupOptions, $this->duplicateLineIds);
    }

    /**
     * @return array{tree: array<int, array<string, mixed>>, revision: string, charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    public function serverTree(): array
    {
        unset($this->tree);

        return [
            'tree' => $this->tree(),
            'revision' => (string) $this->treeRevision(),
            ...$this->totalsPayload(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  array<int, int>  $pendingLocalIds
     * @return array{stale: bool, revision: string, tree: array<int, array<string, mixed>>, conflicts: array<int, string>, cache_token: string}
     */
    #[Renderless]
    public function pullTree(int $baseRevision, array $localRows = [], array $pendingLocalIds = []): array
    {
        $this->refreshEditorContext();

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
            'revision' => (string) $serverRevision,
            'tree' => $tree,
            'conflicts' => $conflicts,
            'cache_token' => $this->editorCacheToken(),
            ...$this->totalsPayload(),
        ];
    }

    public function editorCacheToken(): string
    {
        return $this->opportunity->state->value.':'.$this->opportunity->status;
    }

    /**
     * @return array{charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    private function totalsPayload(): array
    {
        return [
            'charge_total' => (int) ($this->opportunity->charge_total ?? 0),
            'deal_total' => $this->opportunity->deal_total !== null ? (int) $this->opportunity->deal_total : null,
            'has_deal_price' => $this->opportunity->deal_total !== null,
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

    /**
     * @param  \Illuminate\Support\Collection<int, OpportunityItem>  $items
     */
    private function resolveParentPathForText(
        \Illuminate\Support\Collection $items,
        ?string $destination,
    ): ?string {
        $normalized = $this->normalizeGroupKey($destination);

        if ($normalized !== null && $normalized !== '' && $normalized !== 'auto:ungrouped') {
            return $this->treeService()->parentPathForGroupKey($items, $normalized);
        }

        return null;
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

    private function updateNameField(OpportunityItem $item, string $value): void
    {
        if (trim($value) === '' && in_array($item->item_type, [OpportunityItemType::Text, OpportunityItemType::Group], true)) {
            (new RemoveOpportunityItem)($item);

            return;
        }

        (new RenameOpportunityItem)($item, RenameOpportunityItemData::from([
            'name' => trim($value),
        ]));
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

    private function guardFieldsEditable(string $field): void
    {
        if (! $this->opportunity->pricingFrozen()) {
            return;
        }

        $lockedFields = ['quantity', 'unit_price', 'discount_percent', 'name', 'dates', 'starts_at', 'ends_at', 'days'];

        if (in_array($field, $lockedFields, true)) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be edited while pricing is frozen.',
            ]);
        }
    }

    private function guardPricingFrozenForRemoval(): void
    {
        if ($this->opportunity->pricingFrozen()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be removed while pricing is frozen.',
            ]);
        }
    }

    private function refreshOpportunity(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $this->fieldsEditable = $this->editable && ! $this->opportunity->pricingFrozen();

        unset($this->tree, $this->destinations, $this->sectionOptions, $this->parentGroupOptions, $this->duplicateLineIds);

        $this->dispatch(
            'opportunity-totals-updated',
            chargeTotalMinor: (int) ($this->opportunity->charge_total ?? 0),
        );
    }

    /**
     * @param  array{modal?: string|null, modalId?: string|null}  $mutationMeta
     */
    private function runMutation(
        callable $callback,
        ?string $successMessage = null,
        bool $syncTree = false,
        array $mutationMeta = [],
    ): void {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $this->firstMessageFrom($e));

            throw $e;
        }

        if ($syncTree) {
            $this->dispatchTreeMutationDone($mutationMeta);
        }

        if ($successMessage !== null) {
            $this->dispatch('toast', type: 'success', message: $successMessage);
        }
    }

    /**
     * Notify the Alpine table (wire:ignore) to pull fresh server rows and optionally
     * close a modal. Uses a browser event handled in JS so close-modal is always
     * dispatched via Alpine ($dispatch), matching x-signals.modal listeners.
     *
     * @param  array{modal?: string|null, modalId?: string|null}  $meta
     */
    private function dispatchTreeMutationDone(array $meta = []): void
    {
        $this->dispatch(
            'line-items-mutation-done',
            modal: $meta['modal'] ?? null,
            modalId: $meta['modalId'] ?? null,
        );
    }

    private function changeItemDaysAndReturn(OpportunityItem $item, int $days): true
    {
        $this->changeItemDays($item, $days);

        return true;
    }

    private function changeItemDays(OpportunityItem $item, int $days): void
    {
        $days = max(1, $days);
        $opportunity = $item->opportunity()->firstOrFail();

        $startSource = $item->starts_at ?? $opportunity->starts_at ?? Carbon::now('UTC');
        $start = Carbon::parse($startSource)->startOfDay();
        $end = $start->copy()->addDays($days);

        (new ChangeItemDates)($item, ChangeItemDatesData::from([
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
        ]));
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
    $currencySymbol = trim(preg_replace('/[0-9.,\s]/u', '', $formatter->money(0, $opp->currency_code ?? settings('company.base_currency', 'GBP'))));
    $dealTotalRaw = $opp->deal_total !== null ? $opp->formatMoneyCost('deal_total') : '';
    $echoChannel = 'availability.opportunity.'.$opp->id;
    $chargeTotalMinor = (int) ($opp->charge_total ?? 0);
    $pricingFrozen = $opp->pricingFrozen();
    $priceLocked = $opp->hasLocks();
    $canManagePriceLock = Gate::allows('opportunities.unlock_rates');
@endphp

<div
    class="opportunity-line-items-editor w-full"
    wire:ignore
    x-data="window.signals.lineItemsEditor({ oppId: {{ $opp->id }} })"
    x-init="boot()"
    x-on:line-items-mutation-done.window="onMutationDone($event)"
    x-on:opportunity-lifecycle-changed.window="onLifecycleChanged($event)"
    data-server-charge-total-minor="{{ $chargeTotalMinor }}"
>
    {{--
        Frozen config island — first write wins; never re-emitted into x-data.
        Keeping volatile Livewire values OUT of the x-data expression prevents
        Livewire morph from re-initialising Alpine (which left wire:ignore tbody
        rows with dead click bindings after in-session adds).
    --}}
    <script wire:ignore data-lf-seed>
        (function () {
            window.__lfSeed = window.__lfSeed || {};
            window.__lfEditorConfig = window.__lfEditorConfig || {};
            var key = {{ $opp->id }};
            if (!(key in window.__lfSeed)) {
                window.__lfSeed[key] = {
                    tree: @js($this->tree),
                    revision: @js((string) $this->treeRevision()),
                    cacheToken: @js($opp->state->value.':'.$opp->status),
                };
            }
            if (!(key in window.__lfEditorConfig)) {
                window.__lfEditorConfig[key] = {
                    csrfToken: @js(csrf_token()),
                    editable: @js($editable),
                    fieldsEditable: @js($fieldsEditable),
                    pricingFrozen: @js($pricingFrozen),
                    priceLocked: @js($priceLocked),
                    canManagePriceLock: @js($canManagePriceLock),
                    catalogue: @js($catalogue),
                    currencySymbol: @js($currencySymbol),
                    echoChannel: @js($echoChannel),
                    destinations: @js($this->destinations),
                    sectionOptions: @js($this->sectionOptions),
                    serverChargeTotalMinor: @js($chargeTotalMinor),
                    dealTotalRaw: @js($dealTotalRaw),
                    hasDealPrice: @js($opp->deal_total !== null),
                    serverDealTotalMinor: @js($opp->deal_total !== null ? (int) $opp->deal_total : null),
                };
            }
        })();
    </script>

    {{-- Toolbar: quick-add, section actions, sync + deal price --}}
    <div class="lf-toolbar-sticky flex flex-wrap items-center gap-2 mb-3">
        @if($editable)
            <div class="lf-quick-add-bar">
                <div class="lf-quick-add-input-group">
                    <span class="lf-quick-add-prefix" aria-hidden="true">+</span>
                    <div class="relative flex-1 min-w-0">
                        <input
                            type="text"
                            class="s-input w-full"
                            placeholder="Quick add — &ldquo;6 spiider&rdquo;, or a SKU, then Enter"
                            autocomplete="off"
                            x-model="quickAddQuery"
                            x-ref="quickAddInput"
                            x-on:input="onPickerInput($refs.quickAddInput, quickAddQuery, true)"
                            x-on:keydown="onQuickAddInputKeydown($event, $refs.quickAddInput)"
                            x-on:focus="onPickerInput($refs.quickAddInput, quickAddQuery, true)"
                            x-on:blur="closePickerSoon()"
                        >
                        <span class="text-xs text-[var(--text-faint)] font-mono" x-show="quickAddQtyHint" x-text="quickAddQtyHint"></span>
                    </div>
                </div>
                <label class="text-xs text-[var(--text-faint)]">qty</label>
                <input
                    type="number"
                    min="1"
                    step="1"
                    placeholder="1"
                    class="s-input text-center font-mono lf-quick-add-qty"
                    x-ref="quickAddQty"
                    x-model="quickAddQty"
                    x-on:keydown="onQuickAddQtyKeydown($event)"
                >
                <span class="text-xs text-[var(--text-faint)]">into</span>
                <select class="s-input" style="max-width: 230px;" wire:model="quickAddDestination">
                    @foreach($this->destinations as $dest)
                        <option value="{{ $dest['value'] }}" wire:key="dest-{{ $dest['value'] }}">{{ $dest['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="Expand all" x-on:click="expandAll()">
                <flux:icon.arrows-pointing-out class="!size-4" />
            </button>
            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="Collapse all" x-on:click="collapseAll()">
                <flux:icon.arrows-pointing-in class="!size-4" />
            </button>
            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="New section" x-on:click="createInlineSection()">
                <flux:icon.folder-plus class="!size-4" />
            </button>
            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="Add free text item" x-on:click="createInlineTextLine()">
                <flux:icon.document-text class="!size-4" />
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-icon"
                :class="priceLocked ? 'opacity-50 cursor-not-allowed' : ''"
                :disabled="priceLocked"
                :title="priceLocked ? 'Unlock price before setting a deal price' : 'Deal price'"
                x-on:click="!priceLocked && $dispatch('open-modal', 'deal-price')"
            >
                <flux:icon.currency-pound class="!size-4" x-bind:class="hasDealPrice ? '!text-red-600' : ''" />
            </button>
        @endif

        <div class="ml-auto flex items-center gap-2 lf-toolbar-actions">
            <span
                x-show="conflictCount > 0"
                x-cloak
                class="s-badge s-badge-red"
                title="Some lines were changed elsewhere"
            >
                <span class="s-badge-dot"></span>
                <span x-text="conflictCount + ' conflict' + (conflictCount === 1 ? '' : 's')"></span>
            </span>
            <span
                class="s-btn s-btn-ghost s-btn-icon inline-flex items-center justify-center"
                :class="{
                    'text-emerald-600': syncState === 'synced',
                    'text-amber-600': syncState === 'syncing',
                    'text-sky-600': syncState === 'cached',
                    'text-zinc-400': syncState === 'idle',
                }"
                :title="syncLabel + (queue.length ? ' (' + queue.length + ' pending)' : '')"
            >
                <span :class="{ 'animate-spin': syncState === 'syncing' }" class="inline-flex">
                    <flux:icon.arrow-path class="!size-4" />
                </span>
            </span>
        </div>
    </div>

    @if($opp->pricingFrozen() && ! $editable)
        <p class="text-sm text-[var(--text-muted)] mb-3">
            @if($opp->deal_total !== null)
                Deal price: <strong class="font-mono">{{ $formatter->money($opp->deal_total, $opp->currency_code ?? settings('company.base_currency', 'GBP')) }}</strong>
            @elseif($opp->hasLocks())
                Pricing is frozen — rates and tax are locked on this opportunity.
            @endif
        </p>
    @endif

    {{-- Alpine-owned table — Livewire must not morph this subtree --}}
    <x-signals.card class="[&_.s-card-body]:!p-0">
        <div class="overflow-x-auto" wire:ignore>
            <table class="s-table lf-table w-full text-sm select-none">
                <thead>
                    <tr>
                        <th class="text-left" style="min-width: 280px;">Product</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Status</th>
                        <th class="text-left lf-col-qty">Qty</th>
                        <th class="text-left lf-col-days">Days</th>
                        <th class="text-left lf-col-price">Price</th>
                        <th class="text-right lf-col-disc" style="text-align: right;">Disc %</th>
                        <th class="text-right lf-col-charge" style="text-align: right; white-space: nowrap;">
                            <span class="inline-flex items-center justify-end gap-1 w-full">
                                <span>Charge Total</span>
                                <button
                                    type="button"
                                    x-show="priceLocked && canManagePriceLock"
                                    x-cloak
                                    class="inline-flex items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]"
                                    title="Pricing is locked — click to unlock"
                                    @click="openLockPriceModal()"
                                >
                                    <flux:icon.lock-closed class="!size-3.5" />
                                </button>
                            </span>
                        </th>
                        <th class="text-right lf-col-actions"></th>
                    </tr>
                </thead>
                <tbody x-ref="tbody">
                    <template x-for="row in visibleRows" :key="row.id">
                        <tr
                            class="lf-row"
                            :class="{
                                'lf-row-group': row.item_type === 'group',
                                'lf-row-text': row.item_type === 'text',
                                'lf-row-added': row._justAdded,
                                'lf-row-removing': row._removing,
                                'lf-row-nest-target': nestDropGroupId === row.id,
                                'lf-row-dragging': dragId === row.id,
                                'lf-row-shortage': row.has_shortage,
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
                                        x-text="row.is_collapsed ? '+' : '−'"
                                    ></button>
                                    <span x-show="!row.has_children" class="lf-caret-spacer"></span>
                                    <template x-if="row.item_type === 'accessory'">
                                        <span class="text-[var(--text-muted)]">↳</span>
                                    </template>
                                    <template x-if="row.product_url">
                                        <a
                                            :href="row.product_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="lf-name lf-product-link"
                                            :class="{
                                                'italic': row.item_type === 'accessory',
                                            }"
                                            x-text="row.name"
                                        ></a>
                                    </template>
                                    <template x-if="!row.product_url">
                                        <span
                                            class="lf-name"
                                            :class="{
                                                'font-semibold': row.item_type === 'group',
                                                'italic': row.item_type === 'accessory',
                                            }"
                                            @dblclick="fieldsEditable && beginEdit(row.id, 'name', $event)"
                                            x-text="row.name"
                                        ></span>
                                    </template>
                                    <template x-if="row.is_optional">
                                        <span class="s-badge s-badge-zinc ml-1">Optional</span>
                                    </template>
                                </div>
                                <div
                                    x-show="row.item_type !== 'group' && (row.description || row.notes)"
                                    x-cloak
                                    class="mt-0.5 space-y-0.5 text-[11px] leading-snug text-[var(--text-muted)]"
                                    :style="`padding-left:${(row.depth - 1) * 22}px`"
                                >
                                    <div x-show="row.description" x-text="row.description"></div>
                                    <div x-show="row.notes" class="italic">
                                        <span class="not-italic text-[var(--text-faint)]">Warehouse:</span>
                                        <span x-text="row.notes"></span>
                                    </div>
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
                            <td class="text-left tabular-nums lf-col-qty">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="quantity" @click="fieldsEditable && beginEdit(row.id, 'quantity', $event)" x-text="fmtQty(row.quantity)"></span>
                            </td>
                            <td class="text-left tabular-nums lf-col-days">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="days" @click="fieldsEditable && beginEdit(row.id, 'days', $event)" x-text="row.days"></span>
                            </td>
                            <td class="text-left tabular-nums lf-col-price">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left lf-cell-price" data-field="unit_price" @click="fieldsEditable && beginEdit(row.id, 'unit_price', $event)" x-text="row.unit_price_display"></span>
                            </td>
                            <td class="text-right tabular-nums lf-col-disc">
                                <span x-show="row.item_type !== 'group'" class="lf-cell" data-field="discount_percent" @click="fieldsEditable && beginEdit(row.id, 'discount_percent', $event)" x-text="(row.discount_percent ? row.discount_percent : 0) + '%'"></span>
                            </td>
                            <td
                                class="text-right tabular-nums font-medium lf-charge-cell lf-col-charge"
                                style="white-space: nowrap;"
                                @mouseenter="showChargePopover($event, row)"
                                @mouseleave="hideChargePopoverSoon()"
                            >
                                <span x-show="row.item_type === 'group'" class="text-[var(--text-muted)]" x-text="groupSubtotalDisplay(row)"></span>
                                <span
                                    x-show="row.item_type !== 'group'"
                                    class="lf-charge-total"
                                    x-text="row.charge_total_display"
                                ></span>
                            </td>
                            <td class="text-right lf-col-actions">
                                <button type="button" class="s-btn-ghost s-btn-xs s-btn-icon" title="Actions" @click.stop="openRowMenu($event, row)">
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                </button>
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
                        <td colspan="6"></td>
                        <td class="text-right font-semibold whitespace-nowrap">
                            <div>Charge total (ex-tax)</div>
                            <div
                                x-show="displayDealPriceSubline"
                                x-cloak
                                class="text-sm font-normal italic text-[var(--text-muted)] mt-0.5"
                                x-text="displayDealPriceSubline"
                            ></div>
                        </td>
                        <td class="text-right font-mono font-semibold text-lg tabular-nums whitespace-nowrap lf-footer-charge-total">
                            <span class="inline-flex items-center justify-end gap-1.5 w-full">
                                <button
                                    type="button"
                                    x-show="priceLocked && canManagePriceLock"
                                    x-cloak
                                    class="inline-flex items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]"
                                    title="Pricing is locked — click to unlock"
                                    @click="openLockPriceModal()"
                                >
                                    <flux:icon.lock-closed class="!size-4" />
                                </button>
                                <span x-text="displayGrandTotal"></span>
                            </span>
                        </td>
                        <td class="lf-col-actions"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if($editable)
            <div class="lf-add-text-row flex flex-wrap items-stretch gap-2">
                <button type="button" class="s-btn s-btn-ghost lf-add-text-btn" x-on:click="createInlineTextLine()">
                    + Text Item
                </button>
                <button type="button" class="s-btn s-btn-ghost lf-add-text-btn" x-on:click="createInlineSection()">
                    + Section
                </button>
            </div>
        @endif
    </x-signals.card>

    <div x-ref="ghost" class="lf-ghost" x-show="dragId" x-cloak></div>

    {{-- Charge breakdown popover (teleported — avoids table overflow clipping) --}}
    <template x-teleport="body">
        <div
            x-show="chargePopover"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="lf-charge-popover lf-charge-popover--teleport"
            :style="chargePopover ? `position: fixed; z-index: 10050; top: ${chargePopover.top}px; left: ${chargePopover.left}px;` : ''"
            @mouseenter="keepChargePopover()"
            @mouseleave="chargePopover = null"
        >
            <template x-if="chargePopover">
                <div>
                    <div x-text="chargePopover.row.charge_breakdown?.days_line"></div>
                    <div>Rental charge amount: <span x-text="chargePopover.row.charge_breakdown?.rental_charge_display"></span></div>
                    <div>Surcharge amount: <span x-text="chargePopover.row.charge_breakdown?.surcharge_display"></span></div>
                </div>
            </template>
        </div>
    </template>

    {{-- Row actions menu (teleported — same s-dropdown pattern as the opportunities datatable) --}}
    <template x-teleport="body">
        <div
            x-show="openMenu !== null"
            x-cloak
            x-on:click.outside="onRowMenuOutsideClick($event)"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="s-dropdown"
            :style="`position: fixed; z-index: 9999; top: ${menuPos.top}px; right: ${menuPos.right}px; left: auto;`"
        >
            <template x-if="openMenu !== null">
                <div x-show="menuRow" x-cloak>
                    <template x-if="editable && menuRow.item_type === 'group'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="$dispatch('open-modal', { id: 'rename-section', itemId: menuRow.id, name: menuRow.name }); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            Rename section
                        </button>
                    </template>
                    <template x-if="editable && menuRow.item_type === 'text'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="beginEdit(menuRow.id, 'name', $event); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            Rename free text item
                        </button>
                    </template>
                    <template x-if="editable && menuRow.item_type !== 'group' && menuRow.item_type !== 'text'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="openEditLineModal(menuRow); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            Edit line…
                        </button>
                    </template>
                    <template x-if="menuRow.availability_url">
                        <a :href="menuRow.availability_url" target="_blank" rel="noopener" class="s-dropdown-item" style="text-decoration: none;" @click="closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            View availability
                        </a>
                    </template>
                    <template x-if="editable && menuRow.item_type !== 'group' && menuRow.item_type !== 'text'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="toggleOptionalRow(menuRow); closeRowMenu()">
                            <svg x-show="menuRow.is_optional" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <svg x-show="!menuRow.is_optional" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="10"/></svg>
                            <span x-text="menuRow.is_optional ? 'Mark required' : 'Mark optional'"></span>
                        </button>
                    </template>
                    <template x-if="editable && menuRow.has_duplicates">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="mergeDupes(menuRow); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                            Merge duplicates
                        </button>
                    </template>
                    <template x-if="editable && groupMoveOptions(menuRow).length">
                        <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
                        <template x-for="g in groupMoveOptions(menuRow)" :key="g.id">
                            <button type="button" class="s-dropdown-item w-full text-left" @click="assignRowToGroup(menuRow, g.id); closeRowMenu()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span x-text="'Move to ' + g.name"></span>
                            </button>
                        </template>
                    </template>
                    <template x-if="editable && !pricingFrozen">
                        <button
                            type="button"
                            class="s-dropdown-item w-full text-left"
                            :style="idsMatch(confirmDeleteId, menuRow.id) ? 'color: #fff; background: var(--red); width: 100%;' : 'color: var(--red); width: 100%;'"
                            @click.stop="handleRemoveMenuClick()"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
                            <span x-text="idsMatch(confirmDeleteId, menuRow.id) ? 'Click again to confirm' : 'Remove'"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </template>

    {{-- Product picker dropdown --}}
    <template x-teleport="body">
        <div x-ref="pickerDropdown" x-show="picker.open" x-cloak class="s-dropdown"
            style="position: absolute; z-index: 120; min-width: 300px; max-height: 340px; overflow-y: auto;"
            x-on:mousedown.prevent>
            <template x-for="(hit, i) in picker.results" :key="hit.id">
                <button type="button" class="s-dropdown-item flex items-center gap-2.5 w-full text-left lf-picker-hit"
                    :style="i === picker.highlight ? 'background: var(--s-subtle);' : ''"
                    x-on:mousedown.prevent="choosePickerHit(hit)"
                    x-on:mouseenter="picker.highlight = i">
                    <span class="lf-picker-product-thumb shrink-0 flex items-center justify-center overflow-hidden rounded border border-[var(--card-border)] bg-white"
                        style="width: 32px; height: 32px;">
                        <img x-show="hit.image_url" :src="hit.image_url" :alt="hit.name" class="size-full object-cover">
                        <span x-show="!hit.image_url" x-cloak class="font-bold text-[var(--text-muted)]" style="font-family: var(--font-display); font-size: 11px;" x-text="hit.initials || '?'"></span>
                    </span>
                    <span class="flex-1 min-w-0 truncate" x-text="hit.name"></span>
                    <span class="font-mono text-xs text-[var(--text-faint)] shrink-0" x-text="hit.sku || ''"></span>
                </button>
            </template>
        </div>
    </template>

    {{-- Deal price modal --}}
    <x-signals.modal name="deal-price" title="Deal price" id="deal-price-modal">
        <div class="space-y-3">
            <p class="text-sm text-[var(--text-muted)]">
                When set, the opportunity charge total becomes this fixed net amount. Existing line pricing is locked; newly added items are zero-cost.
            </p>
            <div>
                <label class="block text-sm font-medium mb-1">Deal total (ex-tax)</label>
                <input type="text" class="s-input w-full font-mono" x-model="dealPriceInput" placeholder="e.g. 750.00">
            </div>
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'deal-price')">Cancel</button>
                <button type="button" class="s-btn s-btn-ghost" x-show="hasDealPrice" x-on:click="clearDealPrice(); $dispatch('close-modal', 'deal-price')">Clear</button>
                <button type="button" class="s-btn s-btn-primary" x-on:click="applyDealPrice(); $dispatch('close-modal', 'deal-price')">Set deal price</button>
            </x-slot:footer>
        </div>
    </x-signals.modal>

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
        x-on:open-modal.window="if ($event.detail?.id === 'rename-section') { open = true; itemId = $event.detail.itemId; name = $event.detail.name; }"
        x-on:line-items-mutation-done.window="if ($event.detail?.modalId === 'rename-section') open = false">
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
                            x-on:click="if (name.trim()) { $wire.renameItem(itemId, name.trim()); }">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Edit line modal --}}
    <div x-data="{ open: false, line: null, unitPrice: '', discount: '', startsAt: '', endsAt: '', description: '', notes: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'edit-line') {
            open = true; line = $event.detail.line;
            unitPrice = line.unit_price_raw ?? '';
            discount = line.discount_percent ?? '';
            startsAt = line.starts_at ?? '';
            endsAt = line.ends_at ?? '';
            description = line.description ?? '';
            notes = line.notes ?? '';
        }"
        x-on:line-items-mutation-done.window="if ($event.detail?.modalId === 'edit-line') open = false">
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
                        <div>
                            <label class="block text-sm font-medium mb-1">Description</label>
                            <textarea class="s-input w-full" rows="2" x-model="description" placeholder="Customer-facing line description"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Warehouse notes</label>
                            <textarea class="s-input w-full" rows="2" x-model="notes" placeholder="Internal warehouse / prep notes"></textarea>
                        </div>
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="line && $wire.saveLineEdits(
                                line.id,
                                unitPrice === '' ? null : unitPrice,
                                discount === '' ? null : discount,
                                startsAt || null,
                                endsAt || null,
                                description || null,
                                notes || null
                            )"
                        >Save</button>
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
@endpush
