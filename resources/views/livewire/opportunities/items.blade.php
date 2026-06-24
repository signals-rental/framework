<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AssignItemToSection;
use App\Actions\Opportunities\AssignSectionParent;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\CreateOpportunitySection;
use App\Actions\Opportunities\DeleteOpportunitySection;
use App\Actions\Opportunities\MergeOpportunityItems;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\RenameOpportunitySection;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Actions\Opportunities\ReorderOpportunitySections;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\AssignSectionParentData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\RenameOpportunitySectionData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Data\Opportunities\ReorderOpportunitySectionsData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SubstituteItemData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use App\Services\AvailabilityService;
use App\Services\Opportunities\ProductSearchService;
use App\Support\Formatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;

/**
 * Editable line-item editor for an opportunity (M8-3d-ii) — the centrepiece of the
 * opportunity module. Embedded as the Overview tab's main content (the standalone
 * "Line Items" tab/route was removed in the show-page restructure); it nests inside
 * the Show page via `<livewire:opportunities.items :opportunity="…">` and reads as a
 * section, not a second page (the shared header + tabs are owned by the Show page).
 *
 * Every line mutation flows through the SAME event-sourced action classes the API
 * uses (AddOpportunityItem / ChangeItemQuantity / OverrideItemPrice / … ), which
 * authorise `opportunities.edit` internally. After each mutation the opportunity is
 * re-read so live totals (ex-tax model) and per-line availability refresh.
 *
 * Lines are grouped for display: a line with `section_id` sits under its custom
 * {@see OpportunitySection}; otherwise it is auto-grouped by the product's
 * parent-group -> product-group tree. Accessories render as display-only sub-rows
 * (ratio x line qty, zero priced) and are NEVER persisted as opportunity_items.
 *
 * The product picker is a two-tier search: a client-side MiniSearch index built
 * from {@see ProductSearchService::catalogueIndex()} embedded once into the page,
 * plus a debounced server fallback via the {@see searchProducts()} `#[Renderless]`
 * method (the first `#[Renderless]` use in the codebase) backed by the Postgres
 * `pg_trgm` index (degrades to ilike on SQLite).
 */
new class extends Component
{
    public Opportunity $opportunity;

    /**
     * The catalogue payload the client-side MiniSearch index is built from. Embedded
     * once on first render; never re-sent on subsequent Livewire round-trips.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $catalogue = [];

    /** The quick-add bar's destination: 'section:{id}', 'group:{key}', or '' (ungrouped). */
    public string $quickAddDestination = '';

    public bool $editable = false;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->editable = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();

        // Embed the catalogue index once so the client MiniSearch tier is instant.
        $this->catalogue = app(ProductSearchService::class)->catalogueIndex($opportunity->store_id);
    }

    /**
     * Server tier of the two-tier picker. Renderless so the typed-into picker never
     * triggers a component re-render — Alpine merges these hits with the local
     * MiniSearch results. Returns the raw DTO array (product + rate + accessories +
     * live availability for newer / fuzzy-matched catalogue rows).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Renderless]
    public function searchProducts(string $query): array
    {
        Gate::authorize('products.read');

        return app(ProductSearchService::class)
            ->search($query, $this->opportunity->store_id, 12)
            ->map(fn ($result) => $result->toArray())
            ->all();
    }

    /**
     * Add a product as a line item. `productId` is the catalogue Product id; the
     * picker passes the chosen quantity and (optionally) a destination so the new
     * line lands in the right section. Re-reads after committing.
     */
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

            $data = AddOpportunityItemData::from([
                'name' => $product->name,
                'item_id' => $product->id,
                'item_type' => Product::class,
                'quantity' => (string) max(0.01, $quantity),
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
            ]);

            $opportunity = $this->opportunity->fresh() ?? $this->opportunity;
            (new AddOpportunityItem)($opportunity, $data);

            // Land the brand-new line in a real section: the explicit destination if
            // the operator chose one, otherwise find-or-create the auto-group section
            // for the line's product category so every line always has a section_id.
            $this->assignNewestToDestination($destination ?? $this->quickAddDestination);

            $this->refreshOpportunity();
            $this->dispatch('item-added');
        }, 'Item added');
    }

    /**
     * Quick-add bar entry point: a parsed quantity + a resolved product id from the
     * client (the "6 spiider" parsing + picker selection happen in Alpine).
     */
    public function quickAdd(int $productId, float $quantity = 1): void
    {
        $this->addProduct($productId, $quantity, $this->quickAddDestination);
    }

    public function removeItem(int $itemId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId): void {
            $item = $this->findItem($itemId);
            (new RemoveOpportunityItem)($item);

            $this->refreshOpportunity();
        }, 'Item removed');
    }

    public function updateQuantity(int $itemId, string $quantity): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $quantity): void {
            $item = $this->findItem($itemId);
            (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => $quantity]));

            $this->refreshOpportunity();
        }, 'Quantity updated');
    }

    public function overridePrice(int $itemId, ?string $unitPrice): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $unitPrice): void {
            $item = $this->findItem($itemId);
            (new OverrideItemPrice)($item, OverrideItemPriceData::from([
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'unit_price' => $unitPrice === null || $unitPrice === '' ? null : $unitPrice,
            ]));

            $this->refreshOpportunity();
        }, 'Rate updated');
    }

    public function setDiscount(int $itemId, ?string $discountPercent): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $discountPercent): void {
            $item = $this->findItem($itemId);
            (new SetItemDiscount)($item, SetItemDiscountData::from([
                'discount_percent' => $discountPercent === null || $discountPercent === '' ? null : $discountPercent,
            ]));

            $this->refreshOpportunity();
        }, 'Discount updated');
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
                'item_type' => Product::class,
                'name' => $product->name,
            ]));

            $this->refreshOpportunity();
        }, 'Product substituted');
    }

    public string $newSectionName = '';

    /** Optional parent section for the new section (sub-group nesting). '' = top level. */
    public string $newSectionParent = '';

    public function createSection(): void
    {
        $this->guardEditable();

        $name = trim($this->newSectionName);

        if ($name === '') {
            return;
        }

        $opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $parentId = $this->newSectionParent === '' ? null : (int) $this->newSectionParent;
        $nextOrder = (int) $opportunity->sections()
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;

        try {
            (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
                'name' => $name,
                'sort_order' => $nextOrder,
                'parent_id' => $parentId,
            ]));
        } catch (ValidationException $e) {
            // Surface the max-depth (#9) / foreign-parent rejection on the component so
            // the modal stays open with the reason, rather than closing on a failure.
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
        $this->refreshOpportunity();
        // Positional payload: the x-signals.modal listener matches on
        // `$event.detail === name`, so a named-arg dispatch (detail = { name: … })
        // would never match and the modal would stay open. Mirror the Cancel
        // button's `$dispatch('close-modal', 'create-section')`.
        $this->dispatch('close-modal', 'create-section');
        $this->dispatch('toast', type: 'success', message: 'Section created');
    }

    /**
     * Persist a new ordering of sibling sections after a drag. `sectionIds` is the
     * desired order of the dragged section among its siblings; the action writes
     * each section's sort_order to its index. Sections are plain (non-event-sourced)
     * rows, so this never touches the Verbs stream.
     *
     * @param  array<int, int|string>  $sectionIds
     */
    public function reorderSections(array $sectionIds): void
    {
        $this->guardEditable();

        $ids = array_values(array_map('intval', $sectionIds));

        if ($ids === []) {
            return;
        }

        $opportunity = $this->opportunity->fresh() ?? $this->opportunity;

        (new ReorderOpportunitySections)($opportunity, ReorderOpportunitySectionsData::from([
            'section_ids' => $ids,
        ]));

        $this->refreshOpportunity();
    }

    /**
     * Drag-and-drop handler for the section header `wire:sort`. Mirrors the line
     * drag: `parentGroupKey` identifies the destination parent the dragged section
     * landed under ('section-parent:{id}' to nest under another section, or
     * 'section-parent:root' for the top level). The section is (re)parented if its
     * parent changed — respecting the 4-level depth guard, which surfaces a refusal
     * as an error toast and leaves the tree untouched — then its new sibling order
     * is persisted.
     *
     * Sections are plain (non-event-sourced) rows, so this never touches the Verbs
     * stream.
     */
    public function handleSectionSort(int $sectionId, int $position, ?string $parentGroupKey = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($sectionId, $position, $parentGroupKey): void {
            $section = $this->findSection($sectionId);
            $targetParentId = $this->parentIdFromGroupKey($parentGroupKey);

            // Re-parent (and place) the section. AssignSectionParent enforces the
            // depth + cycle guards; on refusal it throws a ValidationException which
            // runMutation turns into an error toast and the tree is left as-is.
            if ($section->parent_id !== $targetParentId) {
                (new AssignSectionParent)($section, AssignSectionParentData::from([
                    'parent_id' => $targetParentId,
                    'sort_order' => $position,
                ]));
            }

            // Re-read so the moved section's parent reflects the assignment, then
            // persist the order of the whole destination sibling set.
            $opportunity = $this->opportunity->fresh(['sections']) ?? $this->opportunity;
            $orderedIds = $this->computeSiblingOrder($opportunity, $sectionId, $position, $targetParentId);

            if (count($orderedIds) > 0) {
                (new ReorderOpportunitySections)($opportunity, ReorderOpportunitySectionsData::from([
                    'section_ids' => $orderedIds,
                ]));
            }

            $this->refreshOpportunity();
        });
    }

    public function renameSection(int $sectionId, string $name): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($sectionId, $name): void {
            $section = $this->findSection($sectionId);
            (new RenameOpportunitySection)($section, RenameOpportunitySectionData::from(['name' => $name]));

            $this->refreshOpportunity();
        }, 'Group renamed');
    }

    public function deleteSection(int $sectionId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($sectionId): void {
            $section = $this->findSection($sectionId);
            (new DeleteOpportunitySection)($section);

            $this->refreshOpportunity();
        }, 'Section deleted');
    }

    /**
     * Drag-and-drop handler for `wire:sort`. `groupId` identifies the destination
     * group/section the line landed in. We first (re)assign the line's section (set
     * when dropped into a custom section, clear when dropped into an auto product
     * group) then persist the new order of that destination's lines.
     */
    public function handleSort(int $itemId, int $position, ?string $groupId = null): void
    {
        $this->guardEditable();

        $item = $this->findItem($itemId);
        $targetSectionId = $this->sectionIdFromGroupKey($groupId);

        if ($item->section_id !== $targetSectionId) {
            (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $targetSectionId]));
        }

        // Re-read so the moved line's group membership reflects the assignment,
        // then persist the order of the whole opportunity (sort_order is global).
        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $orderedIds = $this->computeGlobalOrder($opportunity, $itemId, $position, $targetSectionId);

        if (count($orderedIds) > 0) {
            (new ReorderOpportunityItems)($opportunity, ReorderOpportunityItemsData::from(['item_ids' => $orderedIds]));
        }

        $this->refreshOpportunity();
    }

    /**
     * Assign / clear a line's custom section directly (the per-line menu path).
     */
    public function assignToSection(int $itemId, ?int $sectionId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $sectionId): void {
            $item = $this->findItem($itemId);
            (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $sectionId]));

            $this->refreshOpportunity();

            $label = $sectionId === null
                ? 'auto group'
                : ($this->findSection($sectionId)->name);

            $this->dispatch('toast', type: 'success', message: 'Moved to '.$label);
        });
    }

    /**
     * Merge every duplicate of the given survivor line (same product, transaction
     * type, charge period, hire window, section + optional flag) into it: the
     * survivor's quantity becomes the sum and the duplicates are removed. The
     * per-line "Merge duplicates" affordance calls this with the first line of a
     * duplicate set.
     */
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

    /**
     * The ids of every OTHER line that is the same charge as the given line (and so
     * mergeable into it).
     *
     * @return array<int, int>
     */
    private function duplicateIdsFor(OpportunityItem $survivor): array
    {
        return OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKeyNot($survivor->id)
            ->where('item_id', $survivor->item_id)
            ->where('item_type', $survivor->item_type)
            ->where('transaction_type', $survivor->getRawOriginal('transaction_type'))
            ->where('charge_period', $survivor->getRawOriginal('charge_period'))
            ->where('is_optional', $survivor->is_optional)
            ->where(fn ($q) => $q->where('section_id', $survivor->section_id)
                ->when($survivor->section_id === null, fn ($q) => $q->orWhereNull('section_id')))
            ->where('starts_at', $survivor->starts_at)
            ->where('ends_at', $survivor->ends_at)
            ->whereNotNull('item_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * The set of line ids that have at least one duplicate elsewhere in the
     * opportunity — used to surface the "Merge duplicates" affordance only where it
     * applies. Keyed by line id for an O(1) Blade lookup.
     *
     * @return array<int, bool>
     */
    #[Computed]
    public function duplicateLineIds(): array
    {
        $items = $this->opportunity->items()->get();
        $byKey = [];

        foreach ($items as $item) {
            if ($item->item_id === null) {
                continue;
            }

            $key = implode('|', [
                $item->item_id,
                $item->item_type,
                $item->getRawOriginal('transaction_type'),
                $item->getRawOriginal('charge_period'),
                $item->is_optional ? 1 : 0,
                $item->section_id ?? 'null',
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

    /**
     * The grouped editor model: an ordered list of groups, each carrying its lines,
     * each line carrying its accessories + availability chip + subtotal.
     *
     * Under the unified group model every group is a real, persisted section — auto
     * groups (auto_group_key != null) and user sections are treated IDENTICALLY here
     * (both `kind: 'section'`, both draggable/nestable). The whole tree is the
     * pre-order section walk; lines are bucketed by their section_id beneath their
     * section. A safety fallback still renders any stray null-section lines under a
     * synthesised top-level "Ungrouped" group so nothing ever disappears.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function groups(): array
    {
        $opportunity = $this->opportunity;
        $formatter = app(Formatter::class);

        $opportunity->load([
            'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'sections',
        ]);

        $availability = $this->availabilityMap();
        $items = $opportunity->items;

        // Build a section_id -> section lookup (only sections that still exist).
        $sections = $opportunity->sections->keyBy('id');

        $groups = [];

        // 1. Every section (auto + user), in a parent -> child pre-order so nested
        //    sub-groups render directly beneath their parent (indented by `depth`).
        //    Always shown even when empty so operators can drag lines into them.
        foreach ($this->orderedSections($opportunity->sections) as $entry) {
            $section = $entry['section'];

            $groups['section:'.$section->id] = [
                'key' => 'section:'.$section->id,
                'kind' => 'section',
                'section_id' => $section->id,
                'parent_id' => $section->parent_id,
                'depth' => $entry['depth'],
                'label' => $section->name,
                'lines' => [],
                'subtotal' => 0,
            ];
        }

        // 2. Bucket lines under their section. Stray null-section lines (or lines
        //    pointing at a since-deleted section) fall into a synthesised top-level
        //    "Ungrouped" safety group rather than vanishing — the normal post-backfill
        //    path never reaches it, but a just-deleted section can transiently leave
        //    a line section-less until its next add re-finds an auto group.
        $fallback = null;

        foreach ($items as $item) {
            $lineRow = $this->buildLineRow($item, $availability, $formatter);

            if ($item->section_id !== null && $sections->has($item->section_id)) {
                $groups['section:'.$item->section_id]['lines'][] = $lineRow;
                $groups['section:'.$item->section_id]['subtotal'] += $item->total;

                continue;
            }

            if ($fallback === null) {
                $fallback = [
                    'key' => 'auto:ungrouped',
                    'kind' => 'auto',
                    'section_id' => null,
                    'parent_id' => null,
                    'depth' => 0,
                    'label' => __('Ungrouped'),
                    'lines' => [],
                    'subtotal' => 0,
                ];
            }

            $fallback['lines'][] = $lineRow;
            $fallback['subtotal'] += $item->total;
        }

        $ordered = [];

        foreach (array_values($groups) as $group) {
            $group['subtotal_formatted'] = $formatter->money((int) $group['subtotal']);
            $ordered[] = $group;
        }

        if ($fallback !== null) {
            $fallback['subtotal_formatted'] = $formatter->money((int) $fallback['subtotal']);
            $ordered[] = $fallback;
        }

        return $ordered;
    }

    /**
     * Destination options for the quick-add bar: every custom section plus the auto
     * product groups already present. Value mirrors the group key.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function destinations(): array
    {
        $options = [['value' => '', 'label' => '— Auto group —']];

        foreach ($this->groups as $group) {
            $options[] = [
                'value' => $group['key'],
                'label' => ($group['kind'] === 'section' ? 'Section · ' : 'Group · ').$group['label'],
            ];
        }

        return $options;
    }

    /**
     * Parent-section options for the "New section" modal — every existing section,
     * indented by depth, plus a top-level choice. Lets operators nest a new section
     * under an existing one (sub-groups).
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function sectionOptions(): array
    {
        $this->opportunity->load('sections');

        $options = [['value' => '', 'label' => '— Top level —']];

        foreach ($this->orderedSections($this->opportunity->sections) as $entry) {
            $section = $entry['section'];
            $options[] = [
                'value' => (string) $section->id,
                'label' => str_repeat('— ', $entry['depth']).$section->name,
            ];
        }

        return $options;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Flatten the opportunity's sections into a parent -> child pre-order list,
     * each carrying its nesting depth. Sibling order follows sort_order then id;
     * orphaned children (a missing/foreign parent) are treated as top-level so they
     * never vanish from the editor.
     *
     * @param  \Illuminate\Support\Collection<int, OpportunitySection>  $sections
     * @return array<int, array{section: OpportunitySection, depth: int}>
     */
    private function orderedSections(\Illuminate\Support\Collection $sections): array
    {
        $byParent = $sections
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->groupBy(fn (OpportunitySection $s) => $s->parent_id ?? 0);

        $ids = $sections->pluck('id')->all();
        $ordered = [];

        $walk = function (int $parentKey, int $depth) use (&$walk, $byParent, $ids, &$ordered): void {
            foreach ($byParent->get($parentKey, collect()) as $section) {
                $ordered[] = ['section' => $section, 'depth' => $depth];
                $walk($section->id, $depth + 1);
            }
        };

        // Real top-level sections first (parent_id null).
        $walk(0, 0);

        // Then any orphans whose parent_id points at a row that no longer exists —
        // surface them at the top level rather than dropping them.
        foreach ($byParent as $parentKey => $children) {
            if ($parentKey === 0 || in_array($parentKey, $ids, true)) {
                continue;
            }

            foreach ($children as $section) {
                $ordered[] = ['section' => $section, 'depth' => 0];
                $walk($section->id, 1);
            }
        }

        return $ordered;
    }

    /**
     * @param  array<int, \App\Data\Availability\OpportunityItemAvailabilityData>  $availability
     * @return array<string, mixed>
     */
    private function buildLineRow(OpportunityItem $item, array $availability, Formatter $formatter): array
    {
        $avail = $availability[$item->id] ?? null;

        $productId = ($item->item_type === Product::class) ? $item->item_id : null;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'product_id' => $productId,
            'availability_url' => $productId !== null ? $this->availabilityUrlFor($productId) : null,
            'quantity' => $this->formatQuantity($item->quantity),
            'quantity_raw' => (string) $item->quantity,
            'unit_price' => $formatter->money($item->unit_price ?? 0),
            'unit_price_raw' => $item->formatMoneyCost('unit_price'),
            'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            'total' => $formatter->money($item->total ?? 0),
            'is_optional' => $item->is_optional,
            'section_id' => $item->section_id,
            'starts_at' => optional($item->starts_at)?->toDateString(),
            'ends_at' => optional($item->ends_at)?->toDateString(),
            'charge_period_label' => $item->charge_period->label(),
            'availability' => $avail !== null
                ? ['status' => $this->availabilityChip($avail), 'has_shortage' => $avail->has_shortage]
                : ['status' => null, 'has_shortage' => false],
            'accessories' => $this->accessoriesFor($item),
        ];
    }

    /**
     * Build the deep link to the Gantt availability view for a single product over
     * the opportunity's hire period — the per-line "View availability" menu action.
     * Falls back to the line dates / today when the opportunity carries no period.
     */
    private function availabilityUrlFor(int $productId): string
    {
        $from = optional($this->opportunity->starts_at)?->toDateString();
        $to = optional($this->opportunity->ends_at)?->toDateString();

        $params = array_filter([
            'view' => 'gantt',
            'product' => $productId,
            'store' => $this->opportunity->store_id,
            'from' => $from,
            'to' => $to,
        ], fn ($value) => $value !== null && $value !== '');

        return route('availability.index', $params);
    }

    /**
     * Accessory sub-rows for a line (display-only): ratio x line qty, zero priced.
     * Read from the linked Product's `accessories` relation; never persisted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accessoriesFor(OpportunityItem $item): array
    {
        if ($item->item_id === null || $item->item_type !== Product::class) {
            return [];
        }

        $product = $this->productCache()[$item->item_id] ?? null;

        if ($product === null) {
            return [];
        }

        $lineQty = (float) $item->quantity;

        return $product->accessories
            ->map(function ($accessory) use ($lineQty): array {
                $linked = $accessory->accessoryProduct;

                return [
                    'id' => $accessory->accessory_product_id,
                    'name' => $linked?->name ?? '—',
                    'sku' => $linked?->sku,
                    'ratio' => (string) $accessory->quantity,
                    'quantity' => $this->formatQuantity((float) $accessory->quantity * $lineQty),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-line availability context keyed by opportunity_item_id.
     *
     * @return array<int, \App\Data\Availability\OpportunityItemAvailabilityData>
     */
    private function availabilityMap(): array
    {
        return app(AvailabilityService::class)
            ->getOpportunityContext($this->opportunity->id)
            ->keyBy('opportunity_item_id')
            ->all();
    }

    private function availabilityChip(\App\Data\Availability\OpportunityItemAvailabilityData $avail): string
    {
        if ($avail->has_shortage) {
            return 'out';
        }

        return $avail->available_for_item > 0 ? 'available' : 'reserved';
    }

    /**
     * Cache of the Product rows referenced by the opportunity's lines, with their
     * group + accessories eager-loaded (one query for the whole editor).
     *
     * @return array<int, Product>
     */
    private function productCache(): array
    {
        return once(function (): array {
            $productIds = $this->opportunity->items
                ->where('item_type', Product::class)
                ->pluck('item_id')
                ->filter()
                ->unique()
                ->all();

            if ($productIds === []) {
                return [];
            }

            return Product::query()
                ->whereIn('id', $productIds)
                ->with([
                    'productGroup.parent',
                    'accessories' => fn ($q) => $q->orderBy('sort_order')->with('accessoryProduct'),
                ])
                ->get()
                ->keyBy('id')
                ->all();
        });
    }

    private function sectionIdFromGroupKey(?string $groupKey): ?int
    {
        if ($groupKey !== null && str_starts_with($groupKey, 'section:')) {
            return (int) substr($groupKey, strlen('section:'));
        }

        return null;
    }

    /**
     * Resolve the destination parent section id from a section-drag group key:
     * 'section-parent:{id}' nests under that section; 'section-parent:root' (or any
     * other value) promotes to the top level (null parent).
     */
    private function parentIdFromGroupKey(?string $groupKey): ?int
    {
        if ($groupKey !== null && str_starts_with($groupKey, 'section-parent:')) {
            $value = substr($groupKey, strlen('section-parent:'));

            return $value === 'root' ? null : (int) $value;
        }

        return null;
    }

    /**
     * Compute the new sort order of the destination parent's child sections after a
     * section drag, placing the moved section at the requested position among its new
     * siblings while preserving the relative order of the rest.
     *
     * @return array<int, int>
     */
    private function computeSiblingOrder(Opportunity $opportunity, int $movedId, int $position, ?int $targetParentId): array
    {
        $siblings = $opportunity->sections
            ->where('parent_id', $targetParentId)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->reject(fn (OpportunitySection $s) => $s->id === $movedId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$movedId]);

        return $siblings;
    }

    /**
     * Compute the new global sort order after a drag, placing the moved line at the
     * requested position within its destination group while preserving the relative
     * order of every other line.
     *
     * @return array<int, int>
     */
    private function computeGlobalOrder(Opportunity $opportunity, int $movedId, int $position, ?int $targetSectionId): array
    {
        $items = $opportunity->items->sortBy('sort_order')->values();

        // Lines currently in the destination group (excluding the moved one), in order.
        $destination = $items
            ->filter(fn (OpportunityItem $i) => $i->id !== $movedId && $i->section_id === $targetSectionId)
            ->values();

        $position = max(0, min($position, $destination->count()));

        $reorderedDestination = $destination->all();
        array_splice($reorderedDestination, $position, 0, [$movedId]);
        $reorderedDestinationIds = array_map(
            fn ($entry) => $entry instanceof OpportunityItem ? $entry->id : $entry,
            $reorderedDestination,
        );

        // Stitch: walk the original order, emit destination ids at the slot of the
        // first destination member, drop the moved id from its old slot, keep the rest.
        $ordered = [];
        $emittedDestination = false;
        $destinationIdSet = array_flip($reorderedDestinationIds);

        foreach ($items as $item) {
            if ($item->id === $movedId) {
                continue;
            }

            if (isset($destinationIdSet[$item->id])) {
                if (! $emittedDestination) {
                    foreach ($reorderedDestinationIds as $id) {
                        $ordered[] = $id;
                    }
                    $emittedDestination = true;
                }

                continue;
            }

            $ordered[] = $item->id;
        }

        if (! $emittedDestination) {
            foreach ($reorderedDestinationIds as $id) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }

    private function assignNewestToDestination(?string $destination): void
    {
        $newest = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->orderByDesc('id')
            ->first();

        if ($newest === null) {
            return;
        }

        // An explicit "into …" destination wins. Otherwise (no destination, or an
        // 'auto:…'/'group:…' key) find-or-create the real auto-group section for the
        // line's product category so the line always lands in a persisted section
        // rather than the legacy null-section render path.
        $sectionId = $this->sectionIdFromGroupKey($destination)
            ?? $this->findOrCreateAutoGroupSection($newest)->id;

        (new AssignItemToSection)($newest, AssignItemToSectionData::from(['section_id' => $sectionId]));
    }

    /**
     * Find (or create) the real, persisted auto-group section for a line's product
     * category. Matches on `(opportunity_id, auto_group_key)`; a freshly created
     * auto-group section is appended after every existing section (top level).
     *
     * Plain (non-event-sourced) row, like every other section.
     */
    private function findOrCreateAutoGroupSection(OpportunityItem $item): OpportunitySection
    {
        [$key, $label] = app(\App\Services\Opportunities\OpportunityAutoGroupResolver::class)
            ->resolveLegacySectionKey($item, $this->productCache());

        $existing = OpportunitySection::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->where('auto_group_key', $key)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $nextOrder = (int) OpportunitySection::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereNull('parent_id')
            ->max('sort_order');

        $nextOrder = OpportunitySection::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereNull('parent_id')
            ->exists() ? $nextOrder + 1 : 0;

        return OpportunitySection::create([
            'opportunity_id' => $this->opportunity->id,
            'parent_id' => null,
            'auto_group_key' => $key,
            'name' => $label,
            'sort_order' => $nextOrder,
        ]);
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

    private function findSection(int $sectionId): OpportunitySection
    {
        $section = OpportunitySection::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($sectionId)
            ->first();

        if ($section === null) {
            throw ValidationException::withMessages([
                'section' => 'The section could not be found.',
            ]);
        }

        return $section;
    }

    /**
     * Run a line-item mutation, firing a real-time success toast on completion or an
     * error toast (with the human validation message) on failure. A
     * {@see ValidationException} is re-thrown after the error toast so the component
     * still surfaces field errors (the test suite + inline validation rely on this);
     * the toast is purely additive UX feedback.
     *
     * Pass `$successMessage = null` when the caller dispatches its own context-specific
     * success toast (e.g. the optional/required or move-to-group messages).
     */
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

    /**
     * The first human-readable message from a validation exception, for a toast.
     */
    private function firstMessageFrom(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            foreach ((array) $messages as $message) {
                return (string) $message;
            }
        }

        return $e->getMessage();
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

    /**
     * Re-read the opportunity so totals / availability / grouping recompute, and
     * bust the memoised computed properties.
     *
     * Also notifies the parent Show component (#10): a qty/rate/discount edit
     * recomputes the line + this editor's footer grand total, but the Show
     * component's sidebar Totals panel + header are a SEPARATE Livewire component
     * and would otherwise stay stale. Dispatching `opportunity-totals-updated` lets
     * the Show component refresh its own projection so every total stays in sync.
     */
    private function refreshOpportunity(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;

        unset($this->groups, $this->destinations, $this->sectionOptions, $this->duplicateLineIds);

        $this->dispatch('opportunity-totals-updated');
    }

    private function formatQuantity(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}; ?>

@php
    $opp = $this->opportunity;
    $grandTotal = app(\App\Support\Formatter::class)->money($opp->charge_total ?? 0);
    $groupKeys = array_map(fn ($g) => $g['key'], $this->groups);
    // The editor's currency symbol, derived from a formatted zero so the client-side
    // optimistic totals match the server's currency prefix (e.g. "£", "€", "$").
    $currencySymbol = trim(preg_replace('/[0-9.,\s]/u', '', app(\App\Support\Formatter::class)->money(0)));
@endphp

<section
    class="w-full"
    x-data="opportunityItemsEditor(@js($catalogue), @js($editable), @js($groupKeys), @js($currencySymbol))"
    {{-- The server-authoritative ex-tax charge total (a currency-aware major-unit
         decimal string), re-read on every morph. The footer falls back to this whenever
         the optimistic registry is empty (e.g. after a morph replaces this component
         instance and resets the registry), so the grand total survives the server
         reconcile instead of collapsing to zero. --}}
    data-server-grand-total="{{ $opp->formatMoneyCost('charge_total') }}"
>
    {{-- Embedded line-item editor: the shared header + tabs are owned by the Show
         page that nests this component, so it renders as a section, not a page. --}}
    <div class="flex-1">
        {{-- Quick-add bar + section toolbar --}}
        @if($editable)
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="flex items-center gap-2 flex-1 min-w-[320px] s-panel" style="padding: 8px 10px;">
                    <span class="text-[var(--accent)] font-bold" wire:loading.remove wire:target="addProduct,quickAdd">+</span>
                    <x-signals.spinner size="sm" class="text-[var(--accent)]" wire:loading wire:target="addProduct,quickAdd" />
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
                    {{-- Qty for the quick-add: defaults to 1, overrides any "6 ×" parsed
                         from the search term when a product is chosen from the picker. --}}
                    <label class="text-xs text-[var(--text-faint)]">qty</label>
                    <input
                        type="number" min="1" step="1"
                        class="s-input text-center font-mono"
                        style="width: 64px;"
                        x-model.number="quickAddQty"
                        title="Quantity to add"
                    >
                    <span class="text-xs text-[var(--text-faint)]">into</span>
                    <select class="s-input" style="max-width: 230px;" wire:model="quickAddDestination">
                        @foreach($this->destinations as $dest)
                            <option value="{{ $dest['value'] }}" wire:key="dest-{{ $dest['value'] }}">{{ $dest['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-1">
                    <button type="button" class="s-btn s-btn-ghost" x-on:click="expandAll()" title="Expand all groups">Expand all</button>
                    <button type="button" class="s-btn s-btn-ghost" x-on:click="collapseAll()" title="Collapse all groups">Collapse all</button>
                </div>

                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('open-modal', 'create-section')">
                    + Section
                </button>
            </div>
        @endif

        @if(empty($this->groups))
            <x-signals.empty
                title="No line items"
                description="{{ $editable ? 'Use the quick-add bar above or a blank cell to start building this kit list.' : 'This opportunity has no line items yet.' }}"
            />
        @else
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th style="width: 20px;"></th>
                            <th>Product</th>
                            <th class="text-center" style="width: 96px;">Qty</th>
                            <th class="text-left" style="width: 110px;">Rate</th>
                            <th class="text-left" style="width: 110px;">Discount</th>
                            <th class="text-left" style="width: 120px;">Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    @foreach($this->groups as $group)
                        @php
                            $collapsedKey = $group['key'];
                            $depth = $group['depth'] ?? 0;
                            // The parent key this section header belongs to, for the
                            // section-drag sort group ('root' for a top-level section).
                            $sectionParentKey = $group['kind'] === 'section'
                                ? ('section-parent:'.($group['parent_id'] ?? 'root'))
                                : null;
                        @endphp
                        {{-- Group / section header (its own tbody — valid as a direct table child).
                             For a custom section, the header row is a wire:sort item in the
                             'opportunity-sections' group keyed by its PARENT, so dragging it
                             among its siblings reorders, and dragging it into another section's
                             group (a different parent key) re-parents (nests) it. --}}
                        <tbody wire:key="grp-{{ $group['key'] }}"
                            @if($editable && $group['kind'] === 'section')
                                wire:sort="handleSectionSort"
                                wire:sort:group="opportunity-sections"
                                wire:sort:group-id="{{ $sectionParentKey }}"
                            @endif
                        >
                            <tr class="s-table-group-row"
                                @if($editable && $group['kind'] === 'section')
                                    wire:sort:item="{{ $group['section_id'] }}"
                                @endif
                            >
                                {{-- Drag handle in the leading column, left-aligned in the
                                     SAME leading position as the line-item rows' ⠿ handle.
                                     Auto + user sections are identical here. --}}
                                <td class="text-center">
                                    @if($editable && $group['kind'] === 'section')
                                        <span wire:sort:handle class="cursor-grab text-[var(--text-faint)] select-none" title="Drag to reorder or nest this group">⠿</span>
                                    @endif
                                </td>
                                <td colspan="4">
                                    <div class="inline-flex items-center gap-2" style="padding-left: {{ $depth * 20 }}px;">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 font-semibold"
                                            wire:sort:ignore
                                            x-on:click="toggleGroup('{{ $collapsedKey }}')"
                                        >
                                            <span class="text-xs text-[var(--text-faint)]" x-text="isCollapsed('{{ $collapsedKey }}') ? '▸' : '▾'"></span>
                                            @if($group['kind'] === 'section')
                                                <span class="s-badge s-badge-blue">{{ $depth > 0 ? 'Sub-section' : 'Section' }}</span>
                                            @endif
                                            <span>{{ $group['label'] }}</span>
                                            <span class="text-xs text-[var(--text-faint)]">{{ count($group['lines']) }} {{ \Illuminate\Support\Str::plural('item', count($group['lines'])) }}</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-left font-mono font-semibold">{{ $group['subtotal_formatted'] }}</td>
                                <td class="text-center" @if($editable && $group['kind'] === 'section') wire:sort:ignore @endif>
                                    @if($editable && $group['kind'] === 'section')
                                        <div class="relative inline-block" x-data="rowActionsMenu()">
                                            <button type="button" class="s-btn-icon" x-ref="trigger" x-on:click="toggle()"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteSection({{ $group['section_id'] }}),renameSection({{ $group['section_id'] }})">⋯</button>
                                            <x-signals.spinner size="xs"
                                                wire:loading
                                                wire:target="deleteSection({{ $group['section_id'] }}),renameSection({{ $group['section_id'] }})" />
                                            {{-- Teleported to <body> so the table-wrap overflow can't clip it; positioned
                                                 fixed against the trigger's rect, right-aligned and above content. --}}
                                            <template x-teleport="body">
                                                <div class="s-dropdown" x-show="open" x-cloak
                                                    x-on:click.outside="open = false"
                                                    x-on:keydown.escape.window="open = false"
                                                    :style="menuStyle"
                                                    style="position: fixed; z-index: 1000; min-width: 180px; max-width: 240px;">
                                                    <button type="button" class="s-dropdown-item w-full text-left"
                                                        x-on:click="open = false; $dispatch('open-modal', { id: 'rename-section', sectionId: {{ $group['section_id'] }}, name: @js($group['label']) })">
                                                        Rename
                                                    </button>
                                                    <button type="button" class="s-dropdown-item w-full text-left" style="color: var(--red);"
                                                        x-on:click="open = false"
                                                        wire:click="deleteSection({{ $group['section_id'] }})"
                                                        wire:confirm="Delete this group? Its line items move to Ungrouped until re-grouped.">
                                                        Delete
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </tbody>

                        {{-- Lines (sortable within the group); collapse via x-show keeps the table valid --}}
                        <tbody
                            x-show="!isCollapsed('{{ $collapsedKey }}')"
                            @if($editable)
                                wire:sort="handleSort"
                                wire:sort:group="opportunity-lines"
                                wire:sort:group-id="{{ $group['key'] }}"
                            @endif
                        >
                            @foreach($group['lines'] as $line)
                                        {{-- Each editable line carries its own Alpine state seeded from the
                                             authoritative server values. The qty/rate/discount inputs bind to it
                                             with x-model so the line total (and the footer grand total) update
                                             INSTANTLY client-side; the slow event-sourced server commit then
                                             reconciles by re-seeding on the next morph (the data-* attributes are
                                             re-read whenever Livewire re-renders this wire:key'd row). --}}
                                        <tr class="s-table-line" wire:key="line-{{ $line['id'] }}" wire:sort:item="{{ $line['id'] }}"
                                            @if($editable)
                                                x-data="lineTotalEditor({
                                                    id: {{ $line['id'] }},
                                                    qty: {{ json_encode((float) $line['quantity_raw']) }},
                                                    rate: {{ json_encode($line['unit_price_raw'] === null || $line['unit_price_raw'] === '' ? 0 : (float) $line['unit_price_raw']) }},
                                                    discount: {{ json_encode($line['discount_percent'] === null || $line['discount_percent'] === '' ? 0 : (float) $line['discount_percent']) }},
                                                    serverTotal: {{ json_encode($line['total']) }},
                                                })"
                                                x-init="register()"
                                                :data-server-qty="{{ json_encode((float) $line['quantity_raw']) }}"
                                                :data-server-rate="{{ json_encode($line['unit_price_raw'] === null || $line['unit_price_raw'] === '' ? 0 : (float) $line['unit_price_raw']) }}"
                                                :data-server-discount="{{ json_encode($line['discount_percent'] === null || $line['discount_percent'] === '' ? 0 : (float) $line['discount_percent']) }}"
                                                x-effect="reseed($el.dataset.serverQty, $el.dataset.serverRate, $el.dataset.serverDiscount); qty; rateInput; discount; syncRegistry()"
                                            @endif
                                        >
                                            <td class="text-center">
                                                @if($editable)
                                                    <span wire:sort:handle class="cursor-grab text-[var(--text-faint)] select-none">⠿</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2" wire:sort:ignore style="padding-left: {{ $depth * 20 }}px;">
                                                    <div>
                                                        <div class="font-medium flex items-center gap-2">
                                                            {{ $line['name'] }}
                                                            @php
                                                                $chip = $line['availability']['status'];
                                                                $chipMap = [
                                                                    'available' => ['s-status-green', 'Available'],
                                                                    'reserved' => ['s-status-blue', 'Reserved'],
                                                                    'out' => ['s-status-red', 'Shortage'],
                                                                ];
                                                            @endphp
                                                            @if($chip && isset($chipMap[$chip]))
                                                                <span class="s-status {{ $chipMap[$chip][0] }}"><span class="s-status-dot"></span> {{ $chipMap[$chip][1] }}</span>
                                                            @endif
                                                            @if(count($line['accessories']) > 0)
                                                                <button type="button"
                                                                    class="s-chip text-xs"
                                                                    x-on:click="toggleLine({{ $line['id'] }})"
                                                                    :title="isLineExpanded({{ $line['id'] }}) ? 'Hide accessories' : 'Show {{ count($line['accessories']) }} accessories'">
                                                                    <span x-text="isLineExpanded({{ $line['id'] }}) ? '−' : '+'"></span>{{ count($line['accessories']) }}
                                                                </button>
                                                            @endif
                                                            @if($line['is_optional'])
                                                                <span class="s-badge s-badge-zinc">Optional</span>
                                                            @endif
                                                        </div>
                                                        @if($line['description'])
                                                            <div class="text-xs text-[var(--text-muted)]">{{ $line['description'] }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center" wire:sort:ignore>
                                                @if($editable)
                                                    <div class="inline-flex items-center gap-1">
                                                        <input
                                                            type="number" min="0.01" step="0.01"
                                                            class="s-input text-center font-mono"
                                                            style="width: 84px;"
                                                            x-model.number="qty"
                                                            wire:change="updateQuantity({{ $line['id'] }}, $event.target.value)"
                                                            wire:loading.attr="disabled"
                                                            wire:target="updateQuantity({{ $line['id'] }})"
                                                        >
                                                        <x-signals.spinner size="xs"
                                                            wire:loading
                                                            wire:target="updateQuantity({{ $line['id'] }})" />
                                                    </div>
                                                @else
                                                    <span class="font-mono text-[var(--text-muted)]">{{ $line['quantity'] }}</span>
                                                @endif
                                            </td>
                                            <td class="text-left font-mono" wire:sort:ignore>
                                                @if($editable)
                                                    <div class="inline-flex items-center gap-1">
                                                        <input
                                                            type="text" inputmode="decimal"
                                                            class="s-input font-mono"
                                                            style="width: 96px;"
                                                            x-model="rateInput"
                                                            placeholder="rate"
                                                            title="Unit rate override (blank to use the rate engine)"
                                                            wire:change="overridePrice({{ $line['id'] }}, $event.target.value)"
                                                            wire:loading.attr="disabled"
                                                            wire:target="overridePrice({{ $line['id'] }})"
                                                        >
                                                        <x-signals.spinner size="xs"
                                                            wire:loading
                                                            wire:target="overridePrice({{ $line['id'] }})" />
                                                    </div>
                                                @else
                                                    {{ $line['unit_price'] }}
                                                @endif
                                            </td>
                                            <td class="text-left font-mono" wire:sort:ignore>
                                                @if($editable)
                                                    <div class="inline-flex items-center gap-1">
                                                        <input
                                                            type="number" min="0" max="100" step="0.01"
                                                            class="s-input text-right font-mono"
                                                            style="width: 72px;"
                                                            x-model.number="discount"
                                                            placeholder="0"
                                                            title="Per-line discount %"
                                                            wire:change="setDiscount({{ $line['id'] }}, $event.target.value)"
                                                            wire:loading.attr="disabled"
                                                            wire:target="setDiscount({{ $line['id'] }})"
                                                        >
                                                        <span class="text-xs text-[var(--text-faint)]">%</span>
                                                        <x-signals.spinner size="xs"
                                                            wire:loading
                                                            wire:target="setDiscount({{ $line['id'] }})" />
                                                    </div>
                                                @else
                                                    {{ $line['discount_percent'] !== null ? $line['discount_percent'].'%' : '—' }}
                                                @endif
                                            </td>
                                            <td class="text-left font-mono">
                                                @if($editable)
                                                    {{-- Optimistic line total: x-text shows the instant client-side
                                                         figure (qty × rate × (1 − discount%)); the slow server commit
                                                         reconciles it via the row's reseed effect. --}}
                                                    <span x-text="displayTotal"
                                                        wire:loading.remove
                                                        wire:target="updateQuantity({{ $line['id'] }}),overridePrice({{ $line['id'] }}),setDiscount({{ $line['id'] }}),toggleOptional({{ $line['id'] }}),changeDates({{ $line['id'] }})">{{ $line['total'] }}</span>
                                                    <x-signals.spinner size="xs"
                                                        wire:loading
                                                        wire:target="updateQuantity({{ $line['id'] }}),overridePrice({{ $line['id'] }}),setDiscount({{ $line['id'] }}),toggleOptional({{ $line['id'] }}),changeDates({{ $line['id'] }})" />
                                                @else
                                                    {{ $line['total'] }}
                                                @endif
                                            </td>
                                            <td class="text-center" wire:sort:ignore>
                                                @if($editable)
                                                    <div class="relative inline-block" x-data="rowActionsMenu()">
                                                        <button type="button" class="s-btn-icon" x-ref="trigger" x-on:click="toggle()"
                                                            wire:loading.attr="disabled"
                                                            wire:target="removeItem({{ $line['id'] }}),toggleOptional({{ $line['id'] }}),assignToSection({{ $line['id'] }}),mergeDuplicates({{ $line['id'] }})">⋯</button>
                                                        {{-- Teleported to <body> so the table-wrap overflow can't clip it; positioned
                                                             fixed against the trigger's rect, right-aligned and above content. --}}
                                                        <template x-teleport="body">
                                                            <div class="s-dropdown" x-show="open" x-cloak
                                                                x-on:click.outside="open = false"
                                                                x-on:keydown.escape.window="open = false"
                                                                :style="menuStyle"
                                                                style="position: fixed; z-index: 1000; min-width: 220px; max-width: 280px; max-height: 320px; overflow-y: auto;">
                                                                <button type="button" class="s-dropdown-item w-full text-left"
                                                                    x-on:click="open = false; $dispatch('open-modal', { id: 'edit-line', line: @js($line) })">
                                                                    Edit price / discount / dates
                                                                </button>
                                                                @if($line['product_id'] !== null)
                                                                    <a href="{{ $line['availability_url'] }}" target="_blank" rel="noopener" class="s-dropdown-item w-full text-left block" x-on:click="open = false">
                                                                        View availability
                                                                    </a>
                                                                @endif
                                                                <button type="button" class="s-dropdown-item w-full text-left" x-on:click="open = false" wire:click="toggleOptional({{ $line['id'] }})">
                                                                    {{ $line['is_optional'] ? 'Mark required' : 'Mark optional' }}
                                                                </button>
                                                                <hr class="s-dropdown-sep">
                                                                @if($line['section_id'] !== null)
                                                                    <button type="button" class="s-dropdown-item w-full text-left" x-on:click="open = false" wire:click="assignToSection({{ $line['id'] }}, null)">
                                                                        Move to auto group
                                                                    </button>
                                                                @endif
                                                                @foreach($this->groups as $g)
                                                                    @if($g['kind'] === 'section' && $g['section_id'] !== $line['section_id'])
                                                                        <button type="button" class="s-dropdown-item w-full text-left"
                                                                            wire:key="assign-{{ $line['id'] }}-{{ $g['section_id'] }}"
                                                                            x-on:click="open = false"
                                                                            wire:click="assignToSection({{ $line['id'] }}, {{ $g['section_id'] }})">
                                                                            Move to &ldquo;{{ $g['label'] }}&rdquo;
                                                                        </button>
                                                                    @endif
                                                                @endforeach
                                                                @if(($this->duplicateLineIds[$line['id']] ?? false))
                                                                    <hr class="s-dropdown-sep">
                                                                    <button type="button" class="s-dropdown-item w-full text-left"
                                                                        x-on:click="open = false"
                                                                        wire:click="mergeDuplicates({{ $line['id'] }})"
                                                                        wire:confirm="Merge the matching duplicate line(s) into this one? Their quantities are summed and the duplicates removed.">
                                                                        Merge duplicates into this line
                                                                    </button>
                                                                @endif
                                                                <hr class="s-dropdown-sep">
                                                                <button type="button" class="s-dropdown-item w-full text-left" style="color: var(--red);"
                                                                    x-on:click="open = false"
                                                                    wire:click="removeItem({{ $line['id'] }})"
                                                                    wire:confirm="Remove this line item?">
                                                                    Remove
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>

                                        {{-- Accessory sub-rows (display only): sibling rows, collapsed by
                                             default, toggled per line via the +N chip. --}}
                                        @foreach($line['accessories'] as $accessory)
                                            <tr
                                                class="s-table-accessory"
                                                wire:key="acc-{{ $line['id'] }}-{{ $accessory['id'] }}"
                                                wire:sort:ignore
                                                x-show="isLineExpanded({{ $line['id'] }})"
                                                x-cloak
                                            >
                                                <td></td>
                                                <td>
                                                    <span class="text-[var(--text-muted)] text-sm" style="padding-left: {{ $depth * 20 + 24 }}px;">
                                                        ↳ {{ $accessory['name'] }}
                                                        @if($accessory['sku'])
                                                            <span class="font-mono text-xs text-[var(--text-faint)] ml-2">{{ $accessory['sku'] }} · {{ $accessory['ratio'] }}×</span>
                                                        @endif
                                                    </span>
                                                </td>
                                                <td class="text-center font-mono text-[var(--text-muted)]">{{ $accessory['quantity'] }}</td>
                                                <td class="text-left"><span class="s-chip">incl.</span></td>
                                                <td class="text-left font-mono text-[var(--text-faint)]">—</td>
                                                <td class="text-left font-mono text-[var(--text-faint)]">{{ app(\App\Support\Formatter::class)->money(0) }}</td>
                                                <td></td>
                                            </tr>
                                        @endforeach
                            @endforeach
                        </tbody>
                    @endforeach

                    {{-- Charge total — a final row in line with the Total column (ex-tax).
                         When editable, x-text shows the reactive sum of the per-row optimistic
                         line totals so it updates INSTANTLY; the server-rendered figure is the
                         seed + reconciled fallback. --}}
                    <tfoot>
                        <tr class="s-table-total-row" style="border-top: 2px solid var(--card-border);">
                            <td colspan="5" class="text-right font-semibold">Charge total (ex-tax)</td>
                            <td class="text-left font-mono font-semibold text-lg"
                                @if($editable) x-text="optimisticGrandTotal" @endif
                            >{{ $grandTotal }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </x-signals.table-wrap>
        @endif
    </div>

    {{-- Shared product-picker dropdown. Teleported to <body> with a z-index above the
         edit-line modal backdrop so the substitute search overlays it; positioned in
         Alpine via positionPicker() using document (scroll-offset) coordinates. --}}
    <template x-teleport="body">
        <div
            x-ref="pickerDropdown"
            x-show="picker.open"
            x-cloak
            class="s-dropdown"
            style="position: absolute; z-index: 120; min-width: 300px; max-height: 340px; overflow-y: auto;"
            x-on:mousedown.prevent
        >
            <template x-for="(hit, i) in picker.results" :key="hit.id">
                <button
                    type="button"
                    class="s-dropdown-item flex items-center gap-2 w-full text-left"
                    :style="i === picker.highlight ? 'background: var(--s-subtle);' : ''"
                    x-on:mousedown.prevent="choosePickerHit(hit)"
                    x-on:mouseenter="picker.highlight = i"
                >
                    <span class="flex-1 truncate" x-text="hit.name"></span>
                    <span class="font-mono text-xs text-[var(--text-faint)]" x-text="hit.sku || ''"></span>
                    <template x-if="hit.isNew">
                        <span class="s-badge s-badge-green text-xs">new</span>
                    </template>
                </button>
            </template>
            <template x-if="picker.results.length === 0 && !picker.loading">
                <div class="px-3 py-3 text-center text-[var(--text-faint)] text-sm">No matches</div>
            </template>
            <div class="px-3 py-2 text-xs text-[var(--text-muted)] flex items-center gap-2" style="border-top: 1px solid var(--card-border);">
                <span x-text="'local ' + picker.localCount"></span>
                <template x-if="picker.loading"><span class="ml-auto" style="color: var(--amber);">searching catalogue…</span></template>
                <template x-if="!picker.loading && picker.serverCount > 0"><span x-text="'· server +' + picker.serverCount"></span></template>
            </div>
        </div>
    </template>

    {{-- Create section modal --}}
    <x-signals.modal name="create-section" title="New section" id="create-section-modal">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium mb-1">Section name</label>
                <input type="text" class="s-input w-full" wire:model="newSectionName"
                    wire:keydown.enter.prevent="createSection" placeholder="e.g. Front of House">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nest under (optional)</label>
                <select class="s-input w-full" wire:model="newSectionParent">
                    @foreach($this->sectionOptions as $option)
                        <option value="{{ $option['value'] }}" wire:key="parent-opt-{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[var(--text-muted)] mt-1">Choose a parent section to create a nested sub-group.</p>
            </div>
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'create-section')">Cancel</button>
                <button type="button" class="s-btn s-btn-primary" wire:click="createSection"
                    wire:loading.attr="disabled" wire:target="createSection">
                    <span wire:loading.remove wire:target="createSection">Create</span>
                    <span wire:loading wire:target="createSection" class="inline-flex items-center gap-1.5">
                        <x-signals.spinner size="xs" /> Creating…
                    </span>
                </button>
            </x-slot:footer>
        </div>
    </x-signals.modal>

    {{-- Rename section modal --}}
    <div
        x-data="{ open: false, sectionId: null, name: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'rename-section') { open = true; sectionId = $event.detail.sectionId; name = $event.detail.name; }"
    >
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Rename section</span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body">
                        <label class="block text-sm font-medium mb-1">Section name</label>
                        <input type="text" class="s-input w-full" x-model="name">
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="if (name.trim()) { $wire.renameSection(sectionId, name.trim()); open = false; }">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Edit line modal (price override / discount / dates) --}}
    <div
        x-data="{ open: false, line: null, unitPrice: '', discount: '', startsAt: '', endsAt: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'edit-line') {
            open = true; line = $event.detail.line;
            unitPrice = line.unit_price_raw ?? '';
            discount = line.discount_percent ?? '';
            startsAt = line.starts_at ?? '';
            endsAt = line.ends_at ?? '';
        }"
        x-on:close-edit-line.window="open = false"
    >
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Edit line — <span x-text="line?.name"></span></span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body space-y-3">
                        {{-- Substitute product: reuses the shared two-tier picker. Picking a hit
                             swaps the line's product via the SubstituteItem event action and
                             closes the modal. --}}
                        <div>
                            <label class="block text-sm font-medium mb-1">Substitute product</label>
                            <input
                                type="text"
                                class="s-input w-full"
                                placeholder="Search the catalogue to swap this product…"
                                autocomplete="off"
                                x-ref="substituteInput"
                                x-on:input="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:keydown="onPickerKeydown($event, $refs.substituteInput, false)"
                                x-on:focus="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:blur="closePickerSoon()"
                            >
                            <p class="text-xs text-[var(--text-muted)] mt-1">Keeps the quantity, pricing and dates; only the catalogue product changes.</p>
                        </div>
                        <hr class="s-dropdown-sep">
                        <div>
                            <label class="block text-sm font-medium mb-1">Unit price override (leave blank to use the rate engine)</label>
                            <input type="text" class="s-input w-full font-mono" x-model="unitPrice" placeholder="e.g. 125.00">
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
                                $wire.overridePrice(line.id, unitPrice === '' ? null : unitPrice);
                                $wire.setDiscount(line.id, discount === '' ? null : discount);
                                $wire.changeDates(line.id, startsAt === '' ? null : startsAt, endsAt === '' ? null : endsAt);
                                open = false;
                            ">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</section>

@script
<script>
    Alpine.data('opportunityItemsEditor', (catalogue, editable, groupKeys = [], currencySymbol = '£') => ({
        editable,
        controller: null,
        groupKeys,
        currencySymbol,
        // Registry of per-line optimistic totals (minor-unit-free major decimals),
        // keyed by line id. Rows write into it; the footer reads the reactive sum.
        lineTotals: {},
        collapsedGroups: {},
        expandedLines: {},
        quickAddQty: 1,
        quickAddQtyHint: '',
        picker: {
            open: false,
            target: null,
            isQuickAdd: false,
            results: [],
            highlight: 0,
            loading: false,
            localCount: 0,
            serverCount: 0,
            query: '',
            seq: 0,
            quantity: 1,
            mode: 'add',
            substituteItemId: null,
        },

        init() {
            // Build the two-tier search controller: instant client MiniSearch +
            // debounced server fallback via the #[Renderless] Livewire method.
            this.controller = window.signals.productSearch.createProductSearch({
                catalogue,
                serverSearch: (term) => this.$wire.searchProducts(term),
                limit: 12,
            });

            // Reposition / hide the picker on scroll + resize.
            window.addEventListener('scroll', () => this.positionPicker(), true);
            window.addEventListener('resize', () => this.positionPicker());
        },

        // ---- collapse state ----
        isCollapsed(key) { return !!this.collapsedGroups[key]; },
        toggleGroup(key) { this.collapsedGroups[key] = !this.collapsedGroups[key]; },
        expandAll() { this.collapsedGroups = {}; },
        collapseAll() {
            const next = {};
            (this.groupKeys || []).forEach((k) => { next[k] = true; });
            this.collapsedGroups = next;
        },
        isLineExpanded(id) { return !!this.expandedLines[id]; },
        toggleLine(id) { this.expandedLines[id] = !this.expandedLines[id]; },

        // ---- optimistic totals ----
        // Rows register their current optimistic total (a major-unit number) here so
        // the footer can sum them instantly, ahead of the slow server commit.
        setLineTotal(id, value) { this.lineTotals[id] = value; },
        clearLineTotal(id) { delete this.lineTotals[id]; },

        // The instant ex-tax grand total. While rows are registered (the optimistic
        // path), it's the reactive sum of every registered line total. When the registry
        // is empty — notably right after a Livewire morph replaces this component
        // instance and resets `lineTotals` before the rows re-register — it falls back to
        // the server-authoritative figure carried on the root element's
        // `data-server-grand-total`, so the footer never collapses to £0.00.
        get optimisticGrandTotal() {
            const ids = Object.keys(this.lineTotals);

            if (ids.length === 0) {
                return this.serverGrandTotalFormatted();
            }

            const sum = ids.reduce((acc, id) => acc + (Number(this.lineTotals[id]) || 0), 0);
            return this.formatMoney(sum);
        },

        // The server-rendered authoritative ex-tax charge total, formatted in the
        // editor's currency. Read from the root element's data attribute (re-read on
        // every morph) and reused as the empty-registry fallback above.
        serverGrandTotalFormatted() {
            const raw = this.$root?.dataset?.serverGrandTotal ?? '0';
            return this.formatMoney(Number(raw) || 0);
        },

        // Format a major-unit number as the editor's currency (e.g. "£1,250.00").
        // The server reconciles the authoritative figure; this is the optimistic view.
        formatMoney(amount) {
            const fixed = (Number(amount) || 0).toFixed(2);
            const [whole, frac] = fixed.split('.');
            const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return this.currencySymbol + grouped + '.' + frac;
        },

        // ---- picker ----
        onPickerInput(target, raw, isQuickAdd, opts = {}) {
            const parsed = this.controller.parseQuickAdd(raw);
            this.picker.target = target;
            this.picker.isQuickAdd = isQuickAdd;
            this.picker.quantity = parsed.quantity;
            this.picker.query = parsed.term;
            this.picker.mode = opts.mode || 'add';
            this.picker.substituteItemId = opts.itemId ?? null;

            if (isQuickAdd) {
                // A parsed "6 ×" prefix overrides the qty box; otherwise keep the
                // operator's typed quantity.
                if (parsed.quantity > 1) {
                    this.quickAddQty = parsed.quantity;
                }
                this.quickAddQtyHint = parsed.quantity > 1 ? parsed.quantity + '×' : '';
            }

            if (parsed.term.trim().length < 2) {
                this.picker.results = [];
                this.picker.open = false;
                return;
            }

            // Tier 1: instant local results.
            const local = this.controller.searchLocal(parsed.term);
            this.applyResults(local);
            this.picker.loading = true;
            this.picker.open = true;
            this.positionPicker();

            // Tier 2: debounced server fallback merged into the local results.
            const mySeq = ++this.picker.seq;
            clearTimeout(this._serverTimer);
            this._serverTimer = setTimeout(async () => {
                let server = [];
                try { server = await this.controller.searchServer(parsed.term); } catch (e) { server = []; }
                if (mySeq !== this.picker.seq) { return; }
                this.applyResults(this.controller.merge(local, server));
                this.picker.loading = false;
            }, 250);
        },

        applyResults(results) {
            this.picker.results = results;
            this.picker.highlight = 0;
            this.picker.localCount = results.filter((r) => r.source !== 'server').length;
            this.picker.serverCount = results.filter((r) => r.source === 'server').length;
        },

        onPickerKeydown(event, target, isQuickAdd) {
            if (!this.picker.open) { return; }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight + 1) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.picker.highlight = (this.picker.highlight - 1 + this.picker.results.length) % Math.max(this.picker.results.length, 1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const hit = this.picker.results[this.picker.highlight];
                if (hit) { this.choosePickerHit(hit); }
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                this.picker.open = false;
            }
        },

        choosePickerHit(hit) {
            if (this.picker.mode === 'substitute' && this.picker.substituteItemId) {
                // Swap the line's catalogue product, keeping qty/price/dates intact.
                this.$wire.substituteItem(this.picker.substituteItemId, hit.id);
                this.picker.open = false;
                this.picker.results = [];
                if (this.picker.target) { this.picker.target.value = ''; }
                this.picker.mode = 'add';
                this.picker.substituteItemId = null;
                this.$dispatch('close-edit-line');
                return;
            }

            // Quick-add uses the qty box (which a parsed "6 ×" already updated);
            // the substitute/inline picker uses the parsed quantity.
            const qty = this.picker.isQuickAdd
                ? (Number(this.quickAddQty) > 0 ? Number(this.quickAddQty) : 1)
                : (this.picker.quantity || 1);
            this.$wire.addProduct(hit.id, qty);
            this.picker.open = false;
            this.picker.results = [];
            if (this.picker.isQuickAdd && this.picker.target) {
                this.picker.target.value = '';
                this.quickAddQty = 1;
                this.quickAddQtyHint = '';
                this.$nextTick(() => this.picker.target.focus());
            }
        },

        positionPicker() {
            if (!this.picker.open || !this.picker.target) { return; }
            const rect = this.picker.target.getBoundingClientRect();
            const dd = this.$refs.pickerDropdown;
            dd.style.left = (rect.left + window.scrollX) + 'px';
            dd.style.top = (rect.bottom + window.scrollY + 3) + 'px';
            dd.style.minWidth = Math.max(rect.width, 300) + 'px';
        },

        closePickerSoon() {
            setTimeout(() => { this.picker.open = false; }, 150);
        },
    }));

    // Per-line optimistic-total state. Seeded from the authoritative server values
    // (qty / rate / discount%), it computes the line total INSTANTLY as the operator
    // types and registers it in the parent editor's `lineTotals` registry so the
    // footer grand total updates without waiting for the ~1-2s event-sourced commit.
    // When Livewire morphs the row with the server's authoritative figures, the
    // data-server-* attributes change and `reseed()` pulls the row back to truth.
    Alpine.data('lineTotalEditor', ({ id, qty, rate, discount, serverTotal }) => ({
        lineId: id,
        qty: Number(qty) || 0,
        // The rate input is a string so a blank value (= use the rate engine) is
        // distinguishable from 0; the optimistic maths only runs with a numeric rate.
        rateInput: (rate === null || rate === undefined || rate === '') ? '' : String(rate),
        discount: Number(discount) || 0,
        // The server-rendered formatted total, used as the optimistic display whenever
        // the rate is blank (engine-priced — the client can't recompute it).
        serverTotal,
        _lastSeed: null,

        register() {
            this._lastSeed = this._seedKey(this.qty, this.rateInput, this.discount);
            this.setLineTotal(this.lineId, this.registryTotal());
        },

        destroy() {
            this.clearLineTotal(this.lineId);
        },

        // Re-pull the row to the authoritative server values when Livewire morphs in a
        // fresh render (the data-server-* attributes change). Guarded so it only fires
        // when the seed actually changes, never on the operator's own keystrokes.
        reseed(serverQty, serverRate, serverDiscount) {
            const key = this._seedKey(serverQty, serverRate, serverDiscount);
            if (key === this._lastSeed) { return; }
            this._lastSeed = key;
            this.qty = Number(serverQty) || 0;
            this.rateInput = (serverRate === '' || serverRate === null) ? '' : String(serverRate);
            this.discount = Number(serverDiscount) || 0;
            this.setLineTotal(this.lineId, this.registryTotal());
        },

        _seedKey(q, r, d) {
            return [Number(q) || 0, (r === '' || r === null) ? '' : String(r), Number(d) || 0].join('|');
        },

        // The raw optimistic line total (major units): qty × rate × (1 − discount/100).
        // Negative discounts / blanks are clamped; a blank rate yields null (defer to
        // the server figure).
        numericTotal() {
            if (this.rateInput === '' || this.rateInput === null) { return null; }
            const rate = Number(this.rateInput);
            if (Number.isNaN(rate)) { return null; }
            const disc = Math.min(100, Math.max(0, Number(this.discount) || 0));
            return (Number(this.qty) || 0) * rate * (1 - disc / 100);
        },

        // Parse a formatted server total (e.g. "£1,250.00") to a major-unit number, so
        // engine-priced (blank-rate) lines still contribute their real value to the
        // footer sum instead of registering as zero.
        serverTotalNumeric() {
            const cleaned = String(this.serverTotal ?? '').replace(/[^0-9.\-]/g, '');
            const value = Number(cleaned);
            return Number.isNaN(value) ? 0 : value;
        },

        // The value registered for the footer sum: the optimistic figure when the rate
        // is set, else the parsed server figure (so engine-priced lines still count).
        registryTotal() {
            const total = this.numericTotal();
            return total === null ? this.serverTotalNumeric() : total;
        },

        // Push the current optimistic total into the parent registry. Driven by an
        // x-effect on the row so it re-runs whenever qty/rate/discount change — kept
        // out of the displayTotal getter so the getter stays side-effect-free.
        syncRegistry() {
            this.setLineTotal(this.lineId, this.registryTotal());
        },

        // The formatted total to display in the line's Total cell. Falls back to the
        // server-rendered figure when the rate is engine-priced (blank).
        get displayTotal() {
            const total = this.numericTotal();
            return total === null ? this.serverTotal : this.formatMoney(total);
        },
    }));

    // Row-actions ("⋯") dropdown. Its menu is teleported to <body> so the
    // table-wrap's overflow can never clip it; on open we measure the trigger's
    // viewport rect and position the fixed menu right-aligned beneath it. Scroll
    // / resize closes it (cheaper and simpler than re-anchoring a moving table).
    Alpine.data('rowActionsMenu', () => ({
        open: false,
        menuStyle: '',

        toggle() {
            this.open ? this.close() : this.openMenu();
        },

        openMenu() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            // Right-align the menu's right edge to the trigger's right edge. We MUST
            // null out `left` (the .s-dropdown base rule sets `left: 0`); with both
            // `left` and `right` set a `position: fixed` box stretches to the full
            // viewport width, which is the "full-width menu" bug. Setting `left: auto`
            // lets the menu size to its content (capped by the inline max-width) and
            // anchor by its right edge.
            const right = Math.max(8, window.innerWidth - rect.right);
            const top = rect.bottom + 4;
            this.menuStyle = `top: ${top}px; right: ${right}px; left: auto;`;
            this.open = true;
        },

        close() {
            this.open = false;
        },

        init() {
            this._onViewportChange = () => { if (this.open) this.close(); };
            window.addEventListener('scroll', this._onViewportChange, true);
            window.addEventListener('resize', this._onViewportChange);
        },

        destroy() {
            window.removeEventListener('scroll', this._onViewportChange, true);
            window.removeEventListener('resize', this._onViewportChange);
        },
    }));
</script>
@endscript
