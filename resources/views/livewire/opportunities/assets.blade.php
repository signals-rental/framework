<?php

use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\CheckAsset;
use App\Actions\Opportunities\ClearAssetContainer;
use App\Actions\Opportunities\DeallocateAsset;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\MarkAssetOnHire;
use App\Actions\Opportunities\PrepareAsset;
use App\Actions\Opportunities\QuickAllocateAssets;
use App\Actions\Opportunities\QuickBookOut;
use App\Actions\Opportunities\QuickCheckIn;
use App\Actions\Opportunities\QuickPrepareAssets;
use App\Actions\Opportunities\RevertAssetPreparation;
use App\Actions\Opportunities\RevertAssetStatus;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\SetAssetContainer;
use App\Actions\Opportunities\SubstituteAsset;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\QuickAllocateAssetsData;
use App\Data\Opportunities\QuickBookOutData;
use App\Data\Opportunities\QuickCheckInData;
use App\Data\Opportunities\QuickPrepareAssetsData;
use App\Data\Opportunities\RevertAssetStatusData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\SetAssetContainerData;
use App\Data\Opportunities\SubstituteAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\OpportunityState;
use App\Enums\StockMethod;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\ValueObjects\DispatchGateResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use App\Livewire\Concerns\HasOpportunityActions;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thunk\Verbs\Exceptions\EventNotValid;

/**
 * Opportunity Assets / Fulfilment tab — the industry-standard RMS asset-allocation
 * detail view (opportunity-lifecycle.md §5.4 / §7 / §11).
 *
 * The M5 milestone built the allocate -> prepare -> dispatch -> on-hire -> return ->
 * check actions + their API; this tab is the operator surface. It is a full-width
 * detail view with internal Livewire sub-tabs (a {@see $subTab} property — NOT
 * routes): Functions, Allocate, Prepare, Book out, Check in. Every sub-tab shares ONE
 * grouped asset table (grouped by product) below its controls.
 *
 *  - Functions — a multiselect-driven Action menu mapping to the batch RMS actions
 *    ({@see QuickAllocateAssets} / {@see QuickPrepareAssets} / {@see QuickBookOut} /
 *    {@see RevertAssetStatus} / {@see SetAssetContainer}). Sub-rent supplier /
 *    transfer-in / clear-transfer are the Phase-4 sub-hire/transfer surface and are
 *    rendered honestly DISABLED (never faked).
 *  - Allocate / Prepare / Book out / Check in — a scan bar that resolves a typed
 *    asset number to the action (the full barcode/scanning-abstraction layer is a
 *    later phase; a text + Enter input is the Phase-3 surface).
 *
 * Every mutation calls the SAME action classes the API controller uses (each
 * authorises `opportunities.edit` internally) — this component never self-HTTPs. The
 * per-asset legality is derived generically from the {@see AssetAssignmentStatus}
 * chain (no hardcoded named-status switch); the event guards remain the source of
 * truth (a 422 is caught + flashed).
 *
 * The dispatch path routes through the {@see App\Services\Shortages\DispatchShortageGate}
 * inside the action: a Block store policy throws a 422 (surfaced as the gate reason),
 * a WarnPartial policy proceeds and the action's `gateResult` exposes the held-item
 * metadata, which this component flashes after the dispatch.
 *
 * Reverb-live: subscribes to `availability.opportunity.{id}` so the table refreshes
 * when availability changes elsewhere (mirrors the M8-2 / M8-4c convention).
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    /** Whether the actor may mutate assignments (vs a read-only view). */
    public bool $canEdit = false;

    /** The active internal sub-tab (NOT a route): functions|allocate|prepare|book_out|check_in. */
    public string $subTab = 'functions';

    /** The asset ids ticked in the shared grouped table — drives the Functions Action menu. */
    public array $selected = [];

    /** The chosen Functions Action menu operation, applied to {@see $selected}. */
    public string $bulkAction = '';

    /** The container asset number the Functions "Set container" action targets. */
    public ?string $bulkContainer = null;

    // --- Scan bar (Allocate / Prepare / Book out / Check in) ------------------

    /** The typed asset number the active sub-tab's scan bar will resolve + act on. */
    public ?string $scanAsset = null;

    /** The allocate sub-tab's quantity (whole units of the resolved product to book). */
    public ?string $scanQuantity = '1';

    /** The container asset number the scan bar nests the asset into after the action. */
    public ?string $scanContainer = null;

    /** Allocate sub-tab: allow allocating a free asset not pre-listed against any line. */
    public bool $freeScan = false;

    /** Allocate sub-tab: chain {@see PrepareAsset} immediately after allocating. */
    public bool $markPrepared = false;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load('store');
        $this->canEdit = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Assets');
    }

    /**
     * Live refresh when the opportunity's availability changes (a booking, return,
     * allocation, or stock movement elsewhere). The broadcast is a light signal; the
     * with() computed values re-read the projection on the next render so the
     * assignment list + status chips reflect the recalculated picture.
     */
    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        $this->opportunity->refresh();
    }

    /** Switch the active internal sub-tab, clearing the scan bar between contexts. */
    public function setSubTab(string $subTab): void
    {
        $this->subTab = in_array($subTab, ['functions', 'allocate', 'prepare', 'book_out', 'check_in'], true)
            ? $subTab
            : 'functions';

        $this->resetScanBar();
    }

    protected function resetScanBar(): void
    {
        $this->scanAsset = null;
        $this->scanQuantity = '1';
        $this->scanContainer = null;
    }

    // -------------------------------------------------------------------------
    // Scan bar — each sub-tab resolves the typed asset number then acts on it.
    // NOTE: the scan fields are plain text inputs (resolve asset number -> action
    // on Enter/button); the full barcode/scanning-abstraction layer is a later
    // phase — a text + Enter input is the Phase-3 surface.
    // -------------------------------------------------------------------------

    /**
     * Allocate the scanned asset to a line. Resolves the typed asset number to a free
     * serialised stock level, matches it to a line of the same product (or, with Free
     * Scan, to any line missing capacity), and calls {@see AllocateAsset}. "Mark as
     * prepared" chains {@see PrepareAsset} after the allocation.
     */
    public function scanAllocate(): void
    {
        $number = trim((string) $this->scanAsset);

        if ($number === '') {
            return;
        }

        $this->run(function () use ($number): void {
            $level = $this->resolveFreeStockLevel($number);
            $item = $this->matchLineForStockLevel($level);

            $assignment = (new AllocateAsset)($item, AllocateAssetData::from([
                'stock_level_id' => $level->id,
            ]));

            if ($this->markPrepared) {
                $asset = $this->findAsset($assignment->id);
                (new PrepareAsset)($asset);
            }

            $this->applyScanContainer($assignment->id);
        }, __('Asset allocated.'));

        $this->resetScanBar();
    }

    /** Prepare the scanned (already-allocated) asset via {@see PrepareAsset}. */
    public function scanPrepare(): void
    {
        $this->actOnScannedAssignment(function (OpportunityItemAsset $asset): void {
            (new PrepareAsset)($asset);
            $this->applyScanContainer($asset->id);
        }, __('Asset prepared.'));
    }

    /**
     * Book the scanned asset out via {@see DispatchAsset} — routed through the §7.4
     * dispatch gate: a Block 422 is flashed, a WarnPartial proceeds and the held
     * items are surfaced.
     */
    public function scanBookOut(): void
    {
        $action = new DispatchAsset;

        $this->actOnScannedAssignment(function (OpportunityItemAsset $asset) use ($action): void {
            $action($asset, DispatchAssetData::from([]));
            $this->applyScanContainer($asset->id);
        }, __('Asset booked out.'));

        $this->flashGateWarning($action->gateResult);
    }

    /** Check the scanned asset back in via {@see ReturnAsset}. */
    public function scanCheckIn(): void
    {
        $this->actOnScannedAssignment(function (OpportunityItemAsset $asset): void {
            (new ReturnAsset)($asset, ReturnAssetData::from([]));
            $this->applyScanContainer($asset->id);
        }, __('Asset checked in.'));
    }

    /**
     * Resolve the scan bar's typed asset number to one of this opportunity's existing
     * assignments and run $callback against it. Shared by prepare/book-out/check-in.
     *
     * @param  \Closure(OpportunityItemAsset): void  $callback
     */
    protected function actOnScannedAssignment(\Closure $callback, string $success): void
    {
        $number = trim((string) $this->scanAsset);

        if ($number === '') {
            return;
        }

        $this->run(function () use ($number, $callback): void {
            $callback($this->resolveAssignment($number));
        }, $success);

        $this->resetScanBar();
    }

    /**
     * Nest the just-acted asset into the scanned container, if one was typed, via the
     * {@see SetAssetContainer} M5 action (resolving the container asset number to its
     * stock level). Best-effort: a container miss is flashed but doesn't undo the
     * primary action (it already committed).
     */
    protected function applyScanContainer(int $assignmentId): void
    {
        $container = trim((string) $this->scanContainer);

        if ($container === '') {
            return;
        }

        $level = StockLevel::query()
            ->where('asset_number', $container)
            ->orWhere('serial_number', $container)
            ->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'container' => __('No container was found for ":number".', ['number' => $container]),
            ]);
        }

        (new SetAssetContainer)($this->findAsset($assignmentId), SetAssetContainerData::from([
            'container_stock_level_id' => $level->id,
        ]));
    }

    // -------------------------------------------------------------------------
    // Functions sub-tab — the multiselect Action menu (batch RMS actions)
    // -------------------------------------------------------------------------

    /** Tick / untick every asset row (the table's header checkbox). */
    public function toggleSelectAll(bool $checked): void
    {
        $this->selected = $checked ? array_map('strval', $this->allAssetIds()) : [];
    }

    /**
     * Run the chosen Functions Action against the selected rows. Each branch maps to a
     * real M5 batch/single action; the Phase-4 sub-hire/transfer operations are not
     * selectable (rendered disabled), so they can never reach here.
     */
    public function runBulkAction(): void
    {
        $ids = $this->selectedAssetIds();

        if ($this->bulkAction === '' || $ids === []) {
            return;
        }

        $action = $this->bulkAction;

        if ($action === 'book_out') {
            $this->runBulkBookOut($ids);
            $this->afterBulk();

            return;
        }

        $this->run(function () use ($action, $ids): void {
            match ($action) {
                'auto_allocate' => $this->runBulkAutoAllocate($ids),
                'prepare' => (new QuickPrepareAssets)($this->opportunity, QuickPrepareAssetsData::from([
                    'asset_ids' => $ids,
                ])),
                'revert_status' => $this->runBulkRevert($ids),
                'set_container' => $this->runBulkSetContainer($ids),
                default => throw ValidationException::withMessages([
                    'action' => __('That action is not available.'),
                ]),
            };
        }, $this->bulkSuccessMessage($action));

        $this->afterBulk();
    }

    /**
     * Book the selected assets out in one atomic {@see QuickBookOut} commit, routed
     * through the dispatch gate (a Block 422 rolls the batch back + is flashed; a
     * WarnPartial proceeds and the held items are surfaced).
     */
    protected function runBulkBookOut(array $ids): void
    {
        $action = new QuickBookOut;

        $this->run(function () use ($action, $ids): void {
            $action($this->opportunity, QuickBookOutData::from(['asset_ids' => $ids]));
        }, __('Assets booked out.'));

        $this->flashGateWarning($action->gateResult);
    }

    /**
     * Re-allocate the selected assets to their own lines (a no-op for already
     * allocated assets is impossible — they are; this maps "Auto-allocate" to the RMS
     * quick_allocate of every selected asset's current stock level to its line).
     */
    protected function runBulkAutoAllocate(array $ids): void
    {
        $allocations = $this->opportunity->items
            ->flatMap->assets
            ->whereIn('id', $ids)
            ->filter(fn (OpportunityItemAsset $asset): bool => $asset->stock_level_id !== null)
            ->map(fn (OpportunityItemAsset $asset): array => [
                'opportunity_item_id' => $asset->opportunity_item_id,
                'stock_level_id' => $asset->stock_level_id,
            ])
            ->values()
            ->all();

        if ($allocations === []) {
            return;
        }

        (new QuickAllocateAssets)($this->opportunity, QuickAllocateAssetsData::from([
            'allocations' => $allocations,
        ]));
    }

    /** Revert each selected asset one legal step back along the chain. */
    protected function runBulkRevert(array $ids): void
    {
        foreach ($ids as $id) {
            $asset = $this->findAsset((int) $id);
            $previous = $this->previousStatus($asset->status);

            if ($previous === null) {
                continue;
            }

            (new RevertAssetStatus)($asset, RevertAssetStatusData::from([
                'revert_to' => $previous->value,
            ]));
        }
    }

    /** Nest each selected asset into the typed container via {@see SetAssetContainer}. */
    protected function runBulkSetContainer(array $ids): void
    {
        $number = trim((string) $this->bulkContainer);

        if ($number === '') {
            throw ValidationException::withMessages([
                'container' => __('Enter the container asset number to set.'),
            ]);
        }

        $level = StockLevel::query()
            ->where('asset_number', $number)
            ->orWhere('serial_number', $number)
            ->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'container' => __('No container was found for ":number".', ['number' => $number]),
            ]);
        }

        foreach ($ids as $id) {
            (new SetAssetContainer)($this->findAsset((int) $id), SetAssetContainerData::from([
                'container_stock_level_id' => $level->id,
            ]));
        }
    }

    protected function afterBulk(): void
    {
        $this->bulkAction = '';
        $this->bulkContainer = null;
        $this->selected = [];
        $this->opportunity->refresh();
    }

    protected function bulkSuccessMessage(string $action): string
    {
        return match ($action) {
            'auto_allocate' => __('Assets allocated.'),
            'prepare' => __('Assets prepared.'),
            'revert_status' => __('Asset statuses reverted.'),
            'set_container' => __('Container set.'),
            default => __('Done.'),
        };
    }

    // -------------------------------------------------------------------------
    // Per-row actions (the chevron menu) — single-asset M5 actions
    // -------------------------------------------------------------------------

    public function deallocate(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new DeallocateAsset)($this->findAsset($assetId));
        }, __('Asset deallocated.'));
    }

    public function prepare(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new PrepareAsset)($this->findAsset($assetId));
        }, __('Asset prepared.'));
    }

    public function revertPreparation(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new RevertAssetPreparation)($this->findAsset($assetId));
        }, __('Preparation reverted.'));
    }

    /**
     * Book a serialised asset out. The dispatch gate runs inside the action: a Block
     * store policy throws a 422 (flashed as the gate reason), a WarnPartial store
     * policy proceeds and the action's gateResult carries the held-item metadata,
     * surfaced here as a warning flash.
     */
    public function dispatchAsset(int $assetId): void
    {
        $action = new DispatchAsset;

        $this->run(function () use ($assetId, $action): void {
            $action($this->findAsset($assetId), DispatchAssetData::from([]));
        }, __('Asset booked out.'));

        $this->flashGateWarning($action->gateResult);
    }

    public function markOnHire(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new MarkAssetOnHire)($this->findAsset($assetId));
        }, __('Asset marked on hire.'));
    }

    public function returnAsset(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new ReturnAsset)($this->findAsset($assetId), ReturnAssetData::from([]));
        }, __('Asset checked in.'));
    }

    /** Finalise the condition check on a returned (checked-in) asset, marking it Good. */
    public function check(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new CheckAsset)($this->findAsset($assetId), CheckAssetData::from([
                'condition' => AssetCondition::Good->value,
            ]));
        }, __('Asset finalised.'));
    }

    /** Remove an asset assignment from its container via {@see ClearAssetContainer}. */
    public function clearContainer(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new ClearAssetContainer)($this->findAsset($assetId));
        }, __('Container cleared.'));
    }

    public function substitute(int $assetId): void
    {
        $this->substituteAssetId = $assetId;
        $this->substituteStockLevelId = null;
        $this->substituteReason = null;
    }

    /**
     * Revert an asset one step back along the dispatch/return chain (to correct a
     * mistaken scan). The legal previous status is derived from the chain.
     */
    public function revertStatus(int $assetId): void
    {
        $asset = $this->findAsset($assetId);
        $previous = $this->previousStatus($asset->status);

        if ($previous === null) {
            return;
        }

        $this->run(function () use ($asset, $previous): void {
            (new RevertAssetStatus)($asset, RevertAssetStatusData::from([
                'revert_to' => $previous->value,
            ]));
        }, __('Asset status reverted.'));
    }

    // --- Substitute modal -----------------------------------------------------

    public ?int $substituteAssetId = null;

    public ?int $substituteStockLevelId = null;

    public ?string $substituteReason = null;

    public function confirmSubstitute(): void
    {
        if ($this->substituteAssetId === null || $this->substituteStockLevelId === null) {
            return;
        }

        $this->run(function (): void {
            (new SubstituteAsset)($this->findAsset($this->substituteAssetId), SubstituteAssetData::from([
                'new_stock_level_id' => $this->substituteStockLevelId,
                'reason' => $this->substituteReason,
            ]));
        }, __('Asset substituted.'));

        $this->substituteAssetId = null;
        $this->substituteStockLevelId = null;
        $this->substituteReason = null;
        $this->dispatch('asset-modal-close', name: 'substitute-asset');
    }

    // -------------------------------------------------------------------------
    // Legality (derived from the status chain — never a hardcoded matrix)
    // -------------------------------------------------------------------------

    /**
     * Whether the opportunity is an Order — dispatch/return/on-hire are only legal on
     * an order (the AssetDispatched guard rejects dispatch on a quote).
     */
    public function isOrder(): bool
    {
        return $this->opportunity->state === OpportunityState::Order;
    }

    /**
     * The legal next fulfilment actions for an asset in $status, as a list of
     * {action, label, variant, confirm} descriptors. Derived generically from the
     * status chain; the event guards remain the source of truth.
     *
     * @return list<array{action: string, label: string, variant: string, confirm: string|null}>
     */
    public function assetActions(AssetAssignmentStatus $status): array
    {
        $order = $this->isOrder();
        $actions = [];

        switch ($status) {
            case AssetAssignmentStatus::Allocated:
                $actions[] = $this->descriptor('prepare', __('Prepare'), 'outline-blue');
                if ($order) {
                    $actions[] = $this->descriptor('dispatchAsset', __('Book out'), 'outline-green');
                }
                $actions[] = $this->descriptor('substitute', __('Substitute'), 'ghost');
                $actions[] = $this->descriptor('deallocate', __('Deallocate'), 'ghost', __('Release this asset from the line?'));
                break;

            case AssetAssignmentStatus::Prepared:
                if ($order) {
                    $actions[] = $this->descriptor('dispatchAsset', __('Book out'), 'outline-green');
                }
                $actions[] = $this->descriptor('revertPreparation', __('Un-prepare'), 'ghost');
                $actions[] = $this->descriptor('substitute', __('Substitute'), 'ghost');
                break;

            case AssetAssignmentStatus::Dispatched:
                $actions[] = $this->descriptor('markOnHire', __('Mark on hire'), 'outline-blue');
                $actions[] = $this->descriptor('returnAsset', __('Check in'), 'outline-green');
                $actions[] = $this->descriptor('revertStatus', __('Revert'), 'ghost', __('Revert this asset one step back?'));
                break;

            case AssetAssignmentStatus::OnHire:
                $actions[] = $this->descriptor('returnAsset', __('Check in'), 'outline-green');
                $actions[] = $this->descriptor('revertStatus', __('Revert'), 'ghost', __('Revert this asset one step back?'));
                break;

            case AssetAssignmentStatus::CheckedIn:
                $actions[] = $this->descriptor('check', __('Finalise'), 'outline-green');
                $actions[] = $this->descriptor('revertStatus', __('Revert'), 'ghost', __('Revert this asset one step back?'));
                break;

            case AssetAssignmentStatus::Finalised:
                // Terminal — no further fulfilment action.
                break;
        }

        return $actions;
    }

    /**
     * The status one step back along the dispatch/return chain, or null when the asset
     * is at the start of the revertable range.
     */
    public function previousStatus(AssetAssignmentStatus $status): ?AssetAssignmentStatus
    {
        return match ($status) {
            AssetAssignmentStatus::Dispatched => AssetAssignmentStatus::Prepared,
            AssetAssignmentStatus::OnHire => AssetAssignmentStatus::Dispatched,
            AssetAssignmentStatus::CheckedIn => AssetAssignmentStatus::OnHire,
            AssetAssignmentStatus::Finalised => AssetAssignmentStatus::CheckedIn,
            default => null,
        };
    }

    /**
     * @return array{action: string, label: string, variant: string, confirm: string|null}
     */
    protected function descriptor(string $action, string $label, string $variant, ?string $confirm = null): array
    {
        return ['action' => $action, 'label' => $label, 'variant' => $variant, 'confirm' => $confirm];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Run an assignment mutation, catching the auth/422/404 failures the action
     * classes raise (an illegal transition is a 422) and flashing the first message.
     * The projection is re-read so the next render reflects the new state.
     *
     * @param  \Closure(): void  $action
     */
    protected function run(\Closure $action, string $success): void
    {
        if (! $this->canEdit) {
            session()->flash('error', __('This opportunity is closed or you cannot edit it.'));

            return;
        }

        try {
            $action();
            session()->flash('asset_status', $success);
        } catch (AuthorizationException) {
            session()->flash('error', __('You do not have permission to perform this action.'));
        } catch (NotFoundHttpException) {
            session()->flash('error', __('The scanned asset could not be found for this opportunity.'));
        } catch (ValidationException $e) {
            session()->flash('error', $this->firstHumanError($e));
        } catch (EventNotValid $e) {
            // An illegal transition (e.g. booking out a non-prepared asset, dispatch
            // on a quote) — the Verbs event guard is the source of truth; its message
            // is operator-readable, so surface it directly.
            session()->flash('error', $e->getMessage() ?: __('This action could not be completed.'));
        }

        $this->opportunity->refresh();
    }

    /**
     * The first operator-readable validation message, skipping the machine-readable
     * `code` key the dispatch gate sets alongside its human `shortages` message (so a
     * Block surfaces "Dispatch is blocked: …", not "dispatch_block").
     */
    protected function firstHumanError(ValidationException $e): string
    {
        $errors = $e->errors();

        $human = Arr::except($errors, ['code']);
        $messages = collect($human === [] ? $errors : $human)->flatten();

        return $messages->first() ?? __('This action could not be completed.');
    }

    /**
     * Flash the held-item metadata from a WarnPartial dispatch so the operator sees
     * what was held back (the same metadata the API surfaces in its response meta).
     */
    protected function flashGateWarning(?DispatchGateResult $gateResult): void
    {
        if ($gateResult === null || ! $gateResult->warned()) {
            return;
        }

        $held = collect($gateResult->toHeldItemsMeta()['held_items'] ?? [])
            ->map(fn (array $badge): string => (string) ($badge['product_name'] ?? $badge['label'] ?? __('a line')))
            ->filter()
            ->unique()
            ->implode(', ');

        session()->flash('asset_warning', $held === ''
            ? __('Dispatched with a partial-shipment warning — some items were held back.')
            : __('Dispatched as a partial shipment — held back: :items.', ['items' => $held]));
    }

    /**
     * Resolve a typed asset number to one of this opportunity's existing assignments
     * (the prepare / book-out / check-in scan path).
     */
    protected function resolveAssignment(string $number): OpportunityItemAsset
    {
        $asset = OpportunityItemAsset::query()
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $this->opportunity->id))
            ->whereHas('stockLevel', fn ($q) => $q->where('asset_number', $number)->orWhere('serial_number', $number))
            ->orderBy('id')
            ->first();

        if ($asset === null) {
            throw ValidationException::withMessages([
                'asset' => __('No allocated asset numbered ":number" was found on this opportunity.', ['number' => $number]),
            ]);
        }

        return $asset;
    }

    /**
     * Resolve a typed asset number to a free serialised stock level (the allocate scan
     * path). With Free Scan off the level must belong to a product already on a line.
     */
    protected function resolveFreeStockLevel(string $number): StockLevel
    {
        $level = StockLevel::query()
            ->serialized()
            ->where(fn ($q) => $q->where('asset_number', $number)->orWhere('serial_number', $number))
            ->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'asset' => __('No serialised asset numbered ":number" was found.', ['number' => $number]),
            ]);
        }

        return $level;
    }

    /**
     * Match a free stock level to the line it should be allocated against: the line of
     * the same product that still has capacity. With Free Scan on, fall back to ANY
     * line with capacity (best-effort) when the product does not match a line.
     */
    protected function matchLineForStockLevel(StockLevel $level): OpportunityItem
    {
        $candidate = $this->lineWithCapacity(fn (OpportunityItem $item): bool => $item->isProductBacked()
            && $item->itemable_id === $level->product_id);

        if ($candidate === null && $this->freeScan) {
            $candidate = $this->lineWithCapacity(fn (OpportunityItem $item): bool => $this->itemIsSerialised($item));
        }

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'asset' => __('No line item with remaining capacity matches that asset.'),
            ]);
        }

        return $candidate;
    }

    /**
     * The first line (by display order) satisfying $predicate that still has free
     * allocation capacity (allocated assets < requested quantity).
     *
     * @param  \Closure(OpportunityItem): bool  $predicate
     */
    protected function lineWithCapacity(\Closure $predicate): ?OpportunityItem
    {
        return $this->opportunity->items
            ->sortBy('path')
            ->first(fn (OpportunityItem $item): bool => $predicate($item)
                && $item->assets->count() < (int) ceil((float) $item->quantity));
    }

    protected function findAsset(int $assetId): OpportunityItemAsset
    {
        $asset = OpportunityItemAsset::query()
            ->whereKey($assetId)
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $this->opportunity->id))
            ->first();

        if ($asset === null) {
            throw ValidationException::withMessages(['asset' => __('The asset assignment could not be found.')]);
        }

        return $asset;
    }

    /**
     * The asset ids the operator ticked, intersected with the ids actually on the
     * opportunity (so a stale selection can never act on a foreign asset).
     *
     * @return list<int>
     */
    protected function selectedAssetIds(): array
    {
        $valid = array_flip($this->allAssetIds());

        return collect($this->selected)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => isset($valid[$id]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function allAssetIds(): array
    {
        return $this->opportunity->items
            ->flatMap->assets
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Whether a line is serialised (driven by the product's stock method) and so is
     * fulfilled by allocating specific assets, vs a bulk line dispatched by quantity.
     */
    protected function itemIsSerialised(OpportunityItem $item): bool
    {
        if (! $item->isProductBacked() || $item->itemable_id === null) {
            return $item->assets->isNotEmpty();
        }

        return ($this->productCache()[$item->itemable_id]?->stock_method ?? null) === StockMethod::Serialised;
    }

    /**
     * Cache of the products referenced by the opportunity's lines (one query).
     *
     * @return array<int, Product>
     */
    protected function productCache(): array
    {
        return once(function (): array {
            $ids = $this->opportunity->items
                ->filter(fn (OpportunityItem $item): bool => $item->isProductBacked())
                ->pluck('itemable_id')
                ->filter()
                ->unique()
                ->all();

            if ($ids === []) {
                return [];
            }

            return Product::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
        });
    }

    /**
     * Free serialised stock levels of a line's product for the substitute picker.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    protected function freeAssetsForItem(?int $itemId): Collection
    {
        if ($itemId === null) {
            return collect();
        }

        $item = $this->opportunity->items->firstWhere('id', $itemId);

        if ($item === null || ! $item->isProductBacked() || $item->itemable_id === null) {
            return collect();
        }

        return StockLevel::query()
            ->where('product_id', $item->itemable_id)
            ->serialized()
            ->available()
            ->orderBy('asset_number')
            ->limit(100)
            ->get()
            ->map(fn (StockLevel $level): array => [
                'id' => $level->id,
                'label' => $level->asset_number
                    ?? $level->serial_number
                    ?? $level->item_name
                    ?? (string) $level->id,
            ]);
    }

    /**
     * The line item id the substitute modal's asset belongs to (so its free-asset
     * options can be loaded), or null when the modal is closed.
     */
    protected function substituteAssetItemId(): ?int
    {
        if ($this->substituteAssetId === null) {
            return null;
        }

        return $this->opportunity->items
            ->flatMap->assets
            ->firstWhere('id', $this->substituteAssetId)
            ?->opportunity_item_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->opportunity->load([
            'items' => fn ($q) => $q->orderBy('path')->orderBy('id'),
            'items.assets' => fn ($q) => $q->with(['stockLevel', 'container'])->orderBy('id'),
        ]);

        return [
            ...$this->opportunityActionData(),

            'groups' => $this->buildGroups(),
            'freeAssets' => $this->freeAssetsForItem($this->substituteAssetItemId())->values(),
        ];
    }

    /**
     * Build the grouped asset table model: one group per product line, each carrying
     * the line's per-asset rows + its bulk Qty/Alloc/Out/In tallies. Every sub-tab
     * shares this one table below its controls.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildGroups(): array
    {
        $groups = [];

        foreach ($this->opportunity->items as $item) {
            $serialised = $this->itemIsSerialised($item);

            $rows = [];

            foreach ($item->assets as $asset) {
                $rows[] = [
                    'id' => $asset->id,
                    'asset_number' => $asset->stockLevel?->asset_number
                        ?? $asset->stockLevel?->serial_number
                        ?? $asset->stockLevel?->item_name
                        ?? '#'.$asset->id,
                    'status' => $asset->status,
                    'container' => $asset->container?->asset_number
                        ?? $asset->container?->item_name,
                ];
            }

            $groups[] = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->transaction_type->label(),
                'serialised' => $serialised,
                'rows' => $rows,
                'requested' => (int) ceil((float) $item->quantity),
                'quantity' => $this->formatQuantity($item->quantity),
                'allocated' => $item->assets->count(),
                'dispatched' => $this->formatQuantity($item->dispatched_quantity),
                'returned' => $this->formatQuantity($item->returned_quantity),
            ];
        }

        return $groups;
    }

    protected function formatQuantity(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Assets', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'assets'])

    @php
        $statusBadge = fn (\App\Enums\AssetAssignmentStatus $s) => match ($s) {
            \App\Enums\AssetAssignmentStatus::Allocated => 's-status-zinc',
            \App\Enums\AssetAssignmentStatus::Prepared => 's-status-blue',
            \App\Enums\AssetAssignmentStatus::Dispatched => 's-status-amber',
            \App\Enums\AssetAssignmentStatus::OnHire => 's-status-amber',
            \App\Enums\AssetAssignmentStatus::CheckedIn => 's-status-blue',
            \App\Enums\AssetAssignmentStatus::Finalised => 's-status-green',
        };

        // The internal sub-tabs (Livewire state, NOT routes). The scan-driven four
        // carry a barcode glyph to flag the scan surface.
        $subTabs = [
            ['key' => 'functions', 'label' => __('Functions'), 'scan' => false],
            ['key' => 'allocate', 'label' => __('Allocate'), 'scan' => true],
            ['key' => 'prepare', 'label' => __('Prepare'), 'scan' => true],
            ['key' => 'book_out', 'label' => __('Book out'), 'scan' => true],
            ['key' => 'check_in', 'label' => __('Check in'), 'scan' => true],
        ];
    @endphp

    <div class="flex-1 space-y-5 px-6 py-4 max-md:px-5 max-sm:px-3" x-data="{ subTab: @entangle('subTab') }">

        @if(session('error'))
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        @endif
        @if(session('asset_warning'))
            <x-signals.alert type="warning">{{ session('asset_warning') }}</x-signals.alert>
        @endif
        @if(session('asset_status'))
            <x-signals.alert type="success">{{ session('asset_status') }}</x-signals.alert>
        @endif

        @unless($this->isOrder())
            <x-signals.alert type="info">
                {{ __('Assets can be allocated and prepared on a reserved quotation, but booking out and checking in only become available once it is an order.') }}
            </x-signals.alert>
        @endunless

        {{-- Internal sub-tabs (Livewire, not routes) --}}
        <div class="flex flex-wrap items-center gap-1 border-b border-[var(--border)] pb-px">
            @foreach($subTabs as $tab)
                <button type="button"
                    wire:key="subtab-{{ $tab['key'] }}"
                    wire:click="setSubTab('{{ $tab['key'] }}')"
                    @class([
                        's-tab inline-flex items-center gap-1.5',
                        'on' => $subTab === $tab['key'],
                    ])>
                    @if($tab['scan'])
                        <flux:icon.qr-code class="!size-4" />
                    @endif
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        {{-- ============================================================ --}}
        {{--  SUB-TAB CONTROLS                                            --}}
        {{-- ============================================================ --}}
        @if($canEdit)
            {{-- FUNCTIONS: the multiselect-driven Action toolbar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'functions'" x-cloak>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px]">
                        <label for="bulk-action" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Action') }}</label>
                        <select id="bulk-action" wire:model.live="bulkAction" class="s-input w-full">
                            <option value="">{{ __('Choose an action…') }}</option>
                            <option value="auto_allocate">{{ __('Auto-allocate') }}</option>
                            <option value="prepare">{{ __('Prepare') }}</option>
                            <option value="book_out" @disabled(! $this->isOrder())>{{ __('Book out') }}</option>
                            <option value="revert_status">{{ __('Revert status') }}</option>
                            <option value="set_container">{{ __('Set container') }}</option>
                            {{-- Phase-4 sub-hire / transfer surface — not built; honestly disabled. --}}
                            <option value="" disabled>{{ __('Set sub-rent supplier — coming in Phase 4') }}</option>
                            <option value="" disabled>{{ __('Transfer in — coming in Phase 4') }}</option>
                            <option value="" disabled>{{ __('Clear transfer — coming in Phase 4') }}</option>
                        </select>
                    </div>

                    @if($bulkAction === 'set_container')
                        <div class="min-w-[200px]">
                            <label for="bulk-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container asset no.') }}</label>
                            <input id="bulk-container" type="text" wire:model="bulkContainer" class="s-input w-full font-mono" placeholder="{{ __('Scan / type container') }}">
                        </div>
                    @endif

                    <button type="button"
                        wire:click="runBulkAction"
                        @disabled($bulkAction === '' || count($selected) === 0)
                        class="s-btn s-btn-primary">
                        {{ __('Apply') }}
                        @if(count($selected) > 0)
                            <span class="s-badge s-badge-count">{{ count($selected) }}</span>
                        @endif
                    </button>

                    <p class="text-[11px] text-[var(--text-muted)] self-center">
                        {{ count($selected) === 0 ? __('Select rows below to enable an action.') : trans_choice(':count asset selected|:count assets selected', count($selected), ['count' => count($selected)]) }}
                    </p>
                </div>
            </div>

            {{-- ALLOCATE scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'allocate'" x-cloak>
                <form wire:submit="scanAllocate" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="alloc-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="alloc-asset" type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off">
                    </div>
                    <div class="w-24">
                        <label for="alloc-qty" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Quantity') }}</label>
                        <input id="alloc-qty" type="number" min="1" step="1" wire:model="scanQuantity" class="s-input w-full font-mono">
                    </div>
                    <div class="w-44">
                        <label for="alloc-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="alloc-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}">
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input type="checkbox" wire:model="freeScan" class="s-checkbox"> {{ __('Free scan') }}
                    </label>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input type="checkbox" wire:model="markPrepared" class="s-checkbox"> {{ __('Mark as prepared') }}
                    </label>
                    <button type="submit" class="s-btn s-btn-outline-blue">{{ __('Allocate') }}</button>
                </form>
            </div>

            {{-- PREPARE scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'prepare'" x-cloak>
                <form wire:submit="scanPrepare" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="prep-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="prep-asset" type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off">
                    </div>
                    <div class="w-44">
                        <label for="prep-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="prep-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}">
                    </div>
                    <button type="submit" class="s-btn s-btn-outline-blue">{{ __('Prepare') }}</button>
                </form>
            </div>

            {{-- BOOK OUT scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'book_out'" x-cloak>
                <form wire:submit="scanBookOut" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="out-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="out-asset" type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off" @disabled(! $this->isOrder())>
                    </div>
                    <div class="w-44">
                        <label for="out-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="out-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}" @disabled(! $this->isOrder())>
                    </div>
                    <button type="submit" class="s-btn s-btn-outline-green" @disabled(! $this->isOrder())>{{ __('Book out') }}</button>
                </form>
            </div>

            {{-- CHECK IN scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'check_in'" x-cloak>
                <form wire:submit="scanCheckIn" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="in-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="in-asset" type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off" @disabled(! $this->isOrder())>
                    </div>
                    <div class="w-44">
                        <label for="in-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="in-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}" @disabled(! $this->isOrder())>
                    </div>
                    <button type="submit" class="s-btn s-btn-outline-green" @disabled(! $this->isOrder())>{{ __('Check in') }}</button>
                </form>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{--  SHARED GROUPED ASSET TABLE (all sub-tabs)                    --}}
        {{-- ============================================================ --}}
        @if(empty($groups))
            <x-signals.empty
                title="{{ __('No line items') }}"
                description="{{ __('Add line items on the Overview tab before allocating and dispatching assets.') }}">
                <x-slot:icon><flux:icon.cube class="!size-7" /></x-slot:icon>
            </x-signals.empty>
        @else
            <x-signals.table-wrap>
                <table class="s-table" x-data="assetTable()">
                    <thead>
                        <tr>
                            <th style="width: 32px;">
                                <input type="checkbox" class="s-checkbox"
                                    @if(! $canEdit) disabled @endif
                                    wire:change="toggleSelectAll($event.target.checked)"
                                    @checked(count($selected) > 0 && count($selected) === collect($groups)->sum(fn ($g) => count($g['rows'])))>
                            </th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Asset Number') }}</th>
                            <th style="width: 90px;">{{ __('Type') }}</th>
                            <th style="width: 130px;">{{ __('Status') }}</th>
                            <th style="width: 130px;">{{ __('Container') }}</th>
                            <th class="text-center" style="width: 150px;">{{ __('Qty · Alloc · Out · In') }}</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>

                    @foreach($groups as $group)
                        @php $groupKey = 'asset-grp-'.$group['id']; @endphp
                        {{-- Group header row (product) + per-group expand/lock toggle --}}
                        <tbody wire:key="grp-{{ $group['id'] }}">
                            <tr class="s-table-group-row">
                                <td></td>
                                <td colspan="5">
                                    <button type="button" class="inline-flex items-center gap-2 font-semibold"
                                        x-on:click="toggleGroup('{{ $groupKey }}')">
                                        <span class="text-xs text-[var(--text-faint)]" x-text="isCollapsed('{{ $groupKey }}') ? '▸' : '▾'"></span>
                                        <span>{{ $group['name'] }}</span>
                                        @if(! $group['serialised'])
                                            <span class="s-badge s-badge-zinc s-badge-outline">{{ __('Bulk') }}</span>
                                        @endif
                                        <span class="text-xs text-[var(--text-faint)]">{{ count($group['rows']) }} {{ \Illuminate\Support\Str::plural('asset', count($group['rows'])) }}</span>
                                    </button>
                                </td>
                                <td class="text-center font-mono text-xs text-[var(--text-muted)]">
                                    {{ $group['requested'] }} · {{ $group['allocated'] }} · {{ $group['dispatched'] }} · {{ $group['returned'] }}
                                </td>
                                <td></td>
                            </tr>
                        </tbody>

                        <tbody x-show="!isCollapsed('{{ $groupKey }}')">
                            @forelse($group['rows'] as $row)
                                <tr class="s-table-line" wire:key="asset-{{ $row['id'] }}">
                                    <td class="text-center">
                                        <input type="checkbox" class="s-checkbox" value="{{ $row['id'] }}"
                                            wire:model.live="selected"
                                            @if(! $canEdit) disabled @endif>
                                    </td>
                                    <td class="text-[var(--text-muted)]">{{ $group['name'] }}</td>
                                    <td class="font-medium font-mono">{{ $row['asset_number'] }}</td>
                                    <td>{{ $group['type'] }}</td>
                                    <td>
                                        <span class="s-status {{ $statusBadge($row['status']) }}"><span class="s-status-dot"></span> {{ $row['status']->label() }}</span>
                                    </td>
                                    <td class="text-[var(--text-muted)]">
                                        @if($row['container'])
                                            <span class="s-chip">{{ $row['container'] }}</span>
                                        @else
                                            <span class="text-[var(--text-faint)]">—</span>
                                        @endif
                                    </td>
                                    <td></td>
                                    <td class="text-center">
                                        @if($canEdit && $this->assetActions($row['status']) !== [])
                                            <div class="relative inline-block" x-data="rowActionsMenu()">
                                                <button type="button" class="s-btn-icon" x-ref="trigger" x-on:click="toggle()">▾</button>
                                                {{-- Teleported to <body> so the table-wrap overflow can't clip it. --}}
                                                <template x-teleport="body">
                                                    <div class="s-dropdown" x-show="open" x-cloak
                                                        x-on:click.outside="open = false"
                                                        x-on:keydown.escape.window="open = false"
                                                        :style="menuStyle"
                                                        style="position: fixed; z-index: 1000; min-width: 180px;">
                                                        {{-- The menu is teleported to <body>, OUTSIDE the Livewire
                                                             component root, so `wire:click`/`wire:confirm` lose their
                                                             component binding (Livewire 4) and become no-ops. Calls are
                                                             made through `$wire` (captured from the original Alpine scope,
                                                             preserved across the teleport) so the actions actually fire;
                                                             confirmations use a native confirm() guard in the handler. --}}
                                                        @foreach($this->assetActions($row['status']) as $act)
                                                            <button type="button"
                                                                wire:key="act-{{ $row['id'] }}-{{ $act['action'] }}"
                                                                class="s-dropdown-item w-full text-left"
                                                                @if($act['action'] === 'substitute')
                                                                    x-on:click="open = false; $wire.substitute({{ $row['id'] }}); $dispatch('open-modal', 'substitute-asset')"
                                                                @elseif($act['confirm'])
                                                                    x-on:click="open = false; if (window.confirm(@js($act['confirm']))) { $wire.{{ $act['action'] }}({{ $row['id'] }}); }"
                                                                @else
                                                                    x-on:click="open = false; $wire.{{ $act['action'] }}({{ $row['id'] }})"
                                                                @endif>
                                                                {{ $act['label'] }}
                                                            </button>
                                                        @endforeach
                                                        @if($row['container'])
                                                            <hr class="s-dropdown-sep">
                                                            <button type="button" class="s-dropdown-item w-full text-left"
                                                                x-on:click="open = false; $wire.clearContainer({{ $row['id'] }})">
                                                                {{ __('Clear container') }}
                                                            </button>
                                                        @endif
                                                    </div>
                                                </template>
                                            </div>
                                        @else
                                            <span class="text-[var(--text-faint)]">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr wire:key="grp-empty-{{ $group['id'] }}">
                                    <td></td>
                                    <td colspan="7" class="text-sm text-[var(--text-muted)]">
                                        {{ $group['serialised']
                                            ? __('No assets allocated to this line yet — use the Allocate tab.')
                                            : __('Bulk line — dispatched by quantity (see the tally above).') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endforeach
                </table>
            </x-signals.table-wrap>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{--  SUBSTITUTE-ASSET MODAL                                       --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="substitute-asset" title="{{ __('Substitute asset') }}"
        x-on:asset-modal-close.window="if ($event.detail?.name === 'substitute-asset') open = false">
        <div class="space-y-3">
            <div>
                <label for="substitute-stock-level" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Replacement asset') }}</label>
                @if($freeAssets->isEmpty())
                    <p class="text-sm text-[var(--text-muted)]">{{ __('No free serialised assets are available to substitute in.') }}</p>
                @else
                    <select id="substitute-stock-level" wire:model="substituteStockLevelId" class="s-input w-full">
                        <option value="">{{ __('Select an asset…') }}</option>
                        @foreach($freeAssets as $free)
                            <option value="{{ $free['id'] }}" wire:key="sub-free-{{ $free['id'] }}">{{ $free['label'] }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label for="substitute-reason" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Reason (optional)') }}</label>
                <textarea id="substitute-reason" wire:model="substituteReason" rows="2" class="s-input w-full"></textarea>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'substitute-asset')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="confirmSubstitute" @disabled($freeAssets->isEmpty()) class="s-btn s-btn-primary">{{ __('Substitute') }}</button>
        </x-slot:footer>
    </x-signals.modal>
    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>

@script
<script>
    // Per-group collapse state for the shared asset table (client-only; mirrors the
    // line-item editor's grouping convention).
    Alpine.data('assetTable', () => ({
        collapsedGroups: {},
        isCollapsed(key) { return !!this.collapsedGroups[key]; },
        toggleGroup(key) { this.collapsedGroups[key] = !this.collapsedGroups[key]; },
    }));

    // Row-actions ("▾") dropdown, teleported to <body> so the table-wrap overflow
    // can never clip it; positioned fixed against the trigger's viewport rect.
    Alpine.data('rowActionsMenu', () => ({
        open: false,
        menuStyle: '',
        toggle() { this.open ? this.close() : this.openMenu(); },
        openMenu() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const right = Math.max(8, window.innerWidth - rect.right);
            this.menuStyle = `top: ${rect.bottom + 4}px; right: ${right}px;`;
            this.open = true;
        },
        close() { this.open = false; },
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
