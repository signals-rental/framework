<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Opportunities\AddOpportunityAccessory;
use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AddOpportunityParticipant;
use App\Actions\Opportunities\AdjustBulkQuantity;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CheckAsset;
use App\Actions\Opportunities\ClearAssetContainer;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeallocateAsset;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\LockOpportunity;
use App\Actions\Opportunities\MarkAssetOnHire;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\PrepareAsset;
use App\Actions\Opportunities\QuickAllocateAssets;
use App\Actions\Opportunities\QuickBookOut;
use App\Actions\Opportunities\QuickCheckIn;
use App\Actions\Opportunities\QuickPrepareAssets;
use App\Actions\Opportunities\ReinstateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityCost;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\RemoveOpportunityParticipant;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Actions\Opportunities\ReopenOpportunity;
use App\Actions\Opportunities\RestoreOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\ReturnBulkQuantity;
use App\Actions\Opportunities\RevertAssetPreparation;
use App\Actions\Opportunities\RevertAssetStatus;
use App\Actions\Opportunities\RevertToDraft;
use App\Actions\Opportunities\RevertToQuotation;
use App\Actions\Opportunities\SetAssetContainer;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteAsset;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Actions\Opportunities\UnlockOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Actions\Opportunities\UpdateOpportunityCost;
use App\Actions\Opportunities\UpdateOpportunityParticipant;
use App\Data\Api\ActionLogData;
use App\Data\Availability\OpportunityItemAvailabilityData;
use App\Data\Opportunities\AddOpportunityAccessoryData;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AddOpportunityParticipantData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkAdjustData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\QuickAllocateAssetsData;
use App\Data\Opportunities\QuickBookOutData;
use App\Data\Opportunities\QuickCheckInData;
use App\Data\Opportunities\QuickPrepareAssetsData;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\RevertAssetStatusData;
use App\Data\Opportunities\SetAssetContainerData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SubstituteAssetData;
use App\Data\Opportunities\SubstituteItemData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityParticipantData;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\Rules\DispatchShortageRule;
use App\Guards\Opportunities\Rules\ShortageConfirmationRule;
use App\Guards\Opportunities\TransitionContext;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\ActionLog;
use App\Models\CustomView;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityParticipant;
use App\Services\AvailabilityService;
use App\Services\Opportunities\OpportunityActionDescriber;
use App\ValueObjects\DispatchGateResult;
use Closure;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * API surface for the event-sourced opportunity lifecycle.
 *
 * Reads hit the `opportunities` projection directly (zero replay cost). Every
 * write is delegated to the existing lifecycle actions, which fire Verbs events
 * and dual-write the projection inside a single atomic transaction — the
 * controller never mutates the model directly.
 */
class OpportunityController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'subject',
        'number',
        'reference',
        'state',
        'status',
        'member_id',
        'venue_id',
        'store_id',
        'owned_by',
        'invoiced',
        'has_shortage',
        'use_chargeable_days',
        'open_ended_rental',
        'customer_collecting',
        'customer_returning',
        'source_opportunity_id',
        'tag_list',
        'starts_at',
        'ends_at',
        'charge_starts_at',
        'charge_ends_at',
        'deliver_starts_at',
        'deliver_ends_at',
        'collect_starts_at',
        'collect_ends_at',
        'ordered_at',
        'quote_invalid_at',
        'created_at',
        'updated_at',
    ];

    protected ?string $customFieldModule = 'Opportunity';

    /** @var list<string> */
    protected array $allowedSorts = [
        'subject',
        'number',
        'reference',
        'state',
        'status',
        'charge_total',
        'starts_at',
        'ends_at',
        'charge_starts_at',
        'charge_ends_at',
        'deliver_starts_at',
        'deliver_ends_at',
        'collect_starts_at',
        'collect_ends_at',
        'ordered_at',
        'quote_invalid_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'member',
        'venue',
        'store',
        'owner',
        'deliveryAddress',
        'collectionAddress',
        'sourceOpportunity',
        'items',
        'items.assets',
        'costs',
        'versions',
        'versions.items',
        'participants',
        'participants.member',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'customFieldValues',
    ];

    protected function modelClass(): string
    {
        return Opportunity::class;
    }

    protected function responseDataClass(): string
    {
        return OpportunityData::class;
    }

    protected function createDataClass(): string
    {
        return CreateOpportunityData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateOpportunityData::class;
    }

    protected function createActionClass(): string
    {
        return CreateOpportunity::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateOpportunity::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteOpportunity::class;
    }

    protected function singularKey(): string
    {
        return 'opportunity';
    }

    protected function pluralKey(): string
    {
        return 'opportunities';
    }

    protected function entityType(): string
    {
        return 'opportunities';
    }

    protected function permissions(): array
    {
        return [
            'view' => 'opportunities.view',
            'create' => 'opportunities.create',
            'edit' => 'opportunities.edit',
            'delete' => 'opportunities.delete',
        ];
    }

    protected function abilities(): array
    {
        return ['read' => 'opportunities:read', 'write' => 'opportunities:write'];
    }

    /**
     * List opportunities with filtering, sorting, and pagination.
     *
     * Reads the projection — no event replay. Supports Ransack `q[...]` filters
     * (incl. `q[cf.*]` custom fields), `?include=`, `sort`, and a `view_id` query
     * parameter to apply a saved custom view (its columns project a sparse
     * response; explicit `q` params still take priority over the view's filters).
     */
    #[ApiResponse(200, 'Opportunity list', type: 'array{opportunities: list<array{id: int, subject: string, number: string|null, reference: string|null, state: int, state_label: string, status: int, status_label: string, availability_phase: string, member_id: int|null, store_id: int|null, charge_total: string, invoiced: bool, custom_fields: array<string, mixed>, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');

        $query = Opportunity::query();
        $query = $this->applyIncludes($query, $request);

        ['query' => $query, 'view' => $view] = $this->applyViewOrFilters($query, $request, 'opportunities');

        $paginator = $this->paginateQuery($query, $request);

        $opportunities = $paginator->getCollection()->map(
            fn (Opportunity $opportunity): array => $view !== null
                ? $this->filterResponseByView(OpportunityData::fromModel($opportunity)->toArray(), $view)
                : OpportunityData::fromModel($opportunity)->toArray()
        )->all();

        $meta = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'page' => $paginator->currentPage(),
        ];

        if ($view !== null) {
            $meta['view'] = [
                'id' => $view->id,
                'name' => $view->name,
            ];
        }

        return response()->json([
            'opportunities' => $opportunities,
            'meta' => $meta,
        ]);
    }

    /**
     * Show a single opportunity.
     */
    #[ApiResponse(200, 'Opportunity details', type: 'array{opportunity: array{id: int, subject: string, number: string|null, reference: string|null, state: int, state_label: string, status: int, status_label: string, availability_phase: string, member_id: int|null, store_id: int|null, charge_total: string, invoiced: bool, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');

        $this->applyIncludes(Opportunity::query(), $request, $opportunity);

        return $this->respondWithOpportunityMeta(
            OpportunityData::fromModel($opportunity)->toArray(),
            $opportunity,
        );
    }

    /**
     * List the per-asset assignments across every line item of an opportunity.
     *
     * A flat, paginated view of `opportunity_item_assets` (every item's assets in
     * one read), so the Show page's assets tab does not need the full items
     * payload. Ransack-filterable (`q[status_eq]`, `q[stock_level_id_eq]`,
     * `q[opportunity_item_id_eq]`) and sortable; defaults to oldest-first.
     */
    #[ApiResponse(200, 'Opportunity assets', type: 'array{assets: list<array{id: int, opportunity_item_id: int, stock_level_id: int|null, status: int, status_label: string, allocated_at: string|null, dispatched_at: string|null, returned_at: string|null, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function assets(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');

        $itemIds = $opportunity->allItems()->pluck('id')->all();

        $query = OpportunityItemAsset::query()
            ->whereIn('opportunity_item_id', $itemIds)
            ->with('stockLevel');

        $query = $this->applyFilters($query, $request, [
            'status',
            'stock_level_id',
            'opportunity_item_id',
            'container_stock_level_id',
            'created_at',
            'updated_at',
        ]);
        $query = $this->applySort($query, $request, [
            'status',
            'allocated_at',
            'dispatched_at',
            'returned_at',
            'created_at',
            'updated_at',
        ]);

        if (! $this->hasExplicitSort($request)) {
            $query->oldest();
        }

        $paginator = $this->paginateQuery($query, $request);

        $assets = $paginator->getCollection()->map(
            fn (OpportunityItemAsset $asset): array => OpportunityItemAssetData::fromModel($asset)->toArray()
        )->all();

        return $this->respondWithCollection($assets, 'assets', $paginator);
    }

    /**
     * The per-line availability picture for an opportunity.
     *
     * Delegates to {@see AvailabilityService::getOpportunityContext()} — each
     * product-backed line's units free over its own window at its own store, with
     * the line's OWN demand excluded, plus its shortage shortfall. Lines that
     * reference no product (services, ad-hoc lines) are omitted.
     */
    #[ApiResponse(200, 'Opportunity availability', type: 'array{availability: list<array{opportunity_item_id: int, product_id: int|null, store_id: int, requested_quantity: int, available_for_item: int, shortage_quantity: int, has_shortage: bool, from: string, to: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function availability(Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');

        $context = app(AvailabilityService::class)
            ->getOpportunityContext($opportunity->id)
            ->map(fn (OpportunityItemAvailabilityData $data): array => $data->toArray())
            ->values()
            ->all();

        return $this->respondWithCollection($context, 'availability');
    }

    /**
     * List the scoped audit timeline for an opportunity.
     *
     * A paginated, newest-first read of `action_logs` for this opportunity
     * (`auditable_type = Opportunity::class`, `auditable_id = {id}`), so the Show
     * activity timeline need not know the underlying FQCN. Gated like the global
     * action-log endpoint (`action-log.view` / `action-log:read`).
     */
    #[ApiResponse(200, 'Opportunity activity', type: 'array{activity: list<array{id: int, user_id: int|null, user_name: string|null, action: string, auditable_type: string|null, auditable_id: int|null, old_values: array<string, mixed>|null, new_values: array<string, mixed>|null, ip_address: string|null, created_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function activity(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('action-log.view', 'action-log:read');

        $query = ActionLog::query()
            ->with('user')
            ->where('auditable_type', Opportunity::class)
            ->where('auditable_id', $opportunity->id)
            ->latest();

        $paginator = $this->paginateQuery($query, $request);

        $activity = $paginator->getCollection()->map(
            fn (ActionLog $log): array => ActionLogData::fromModel($log)->toArray()
        )->all();

        return $this->respondWithCollection($activity, 'activity', $paginator);
    }

    /**
     * Enumerate the lifecycle actions available on an opportunity, each annotated
     * with whether the current actor + state allows it and, if not, a
     * machine-readable reason (opportunity-lifecycle.md §12.2).
     *
     * The Show-page toolbar renders from this: for every transition it builds the
     * appropriate {@see TransitionContext} and runs the NON-throwing,
     * side-effect-free {@see GuardPipeline::check()} (permission + business rules +
     * approval/plugin seams) plus a generic state precondition. A denial carries a
     * stable `code` (`fx_tax_locked`, `shortage_block`, `permission_denied`,
     * `invalid_state`, `nothing_to_unlock`, `dispatched`) so the UI can branch —
     * e.g. render an "Unlock rates" CTA on `fx_tax_locked`. Nothing is mutated.
     */
    #[ApiResponse(200, 'Available actions', type: 'array{available_actions: list<array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}>}')]
    public function availableActions(Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');

        $status = $opportunity->statusEnum();
        $isDraft = $opportunity->state === OpportunityState::Draft;
        $isQuotation = $opportunity->state === OpportunityState::Quotation;
        $isOrder = $opportunity->state === OpportunityState::Order;
        $hasLocks = (bool) $opportunity->exchange_rate_locked || (bool) $opportunity->tax_locked;

        $actions = [
            // Draft → Quotation.
            $this->describeAction($opportunity, 'convert_to_quotation', 'Convert to Quotation', 'opportunities.edit', ConvertToQuotation::TRANSITION,
                statePrecondition: fn (): ?array => $isDraft ? null : ['Only a draft can be converted to a quotation.', 'invalid_state']),

            // Quotation → Order (runs the shortage confirmation gate precheck).
            $this->describeAction($opportunity, 'convert_to_order', 'Convert to Order', 'opportunities.edit', ShortageConfirmationRule::TRANSITION,
                statePrecondition: fn (): ?array => $isQuotation && ! $status->isClosed() ? null : ['Only an open quotation can be converted to an order.', 'invalid_state']),

            // Within-state status move.
            $this->describeAction($opportunity, 'change_status', 'Change Status', 'opportunities.edit', ChangeOpportunityStatus::TRANSITION,
                statePrecondition: fn (): ?array => $status->isClosed() ? ['A closed opportunity cannot change status.', 'invalid_state'] : null),

            // Lost/Dead/Postponed/Cancelled → active.
            $this->describeAction($opportunity, 'reinstate', 'Reinstate', 'opportunities.edit', ReinstateOpportunity::TRANSITION,
                statePrecondition: fn (): ?array => $status->isReinstatable() ? null : ['Only a lost, dead, postponed, or cancelled opportunity can be reinstated.', 'invalid_state']),

            // Complete (terminal) → active order.
            $this->describeAction($opportunity, 'reopen', 'Re-open', 'opportunities.edit', ReopenOpportunity::TRANSITION,
                statePrecondition: fn (): ?array => $status->isTerminalComplete() ? null : ['Only a completed order can be re-opened.', 'invalid_state']),

            // Order → Quotation (nothing dispatched).
            $this->describeAction($opportunity, 'revert_to_quotation', 'Revert to Quotation', 'opportunities.edit', RevertToQuotation::TRANSITION,
                statePrecondition: fn (): ?array => $this->revertToQuotationPrecondition($opportunity, $isOrder, $status->isClosed())),

            // Quotation → Draft (open/provisional quote only).
            $this->describeAction($opportunity, 'revert_to_draft', 'Revert to Draft', 'opportunities.edit', RevertToDraft::TRANSITION,
                statePrecondition: fn (): ?array => $isQuotation && $status->isRevertibleToDraft() ? null : ['Only an open, provisional quotation can be reverted to a draft.', 'invalid_state']),

            // Release FX/tax locks.
            $this->describeAction($opportunity, 'unlock_locks', $hasLocks ? 'Unlock price' : 'Lock price', 'opportunities.unlock_rates', null,
                statePrecondition: fn (): ?array => (! $hasLocks && $opportunity->deal_total !== null)
                    ? ['Clear the deal price before locking price.', 'deal_price_active']
                    : null),

            // Clone — always available to a creator.
            $this->describeAction($opportunity, 'clone', 'Clone', 'opportunities.create', null),

            // Dispatch — relevant once the order has allocated assets.
            $this->describeAction($opportunity, 'dispatch', 'Dispatch', 'opportunities.edit', DispatchShortageRule::TRANSITION,
                statePrecondition: fn (): ?array => $isOrder && ! $status->isClosed() ? null : ['Assets can only be dispatched on an open order.', 'invalid_state']),

            // Soft-delete.
            $this->describeAction($opportunity, 'delete', 'Delete', 'opportunities.delete', null),
        ];

        return response()->json(['available_actions' => $actions]);
    }

    /**
     * Create a new opportunity.
     *
     * The opportunity is created as a Draft via the OpportunityCreated event.
     */
    #[ApiResponse(201, 'Opportunity created')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('opportunities.create', 'opportunities:write');

        $validated = $request->validate(CreateOpportunityData::rules());
        $result = (new CreateOpportunity)(CreateOpportunityData::from($validated));

        return $this->respondWithFreshOpportunity($result->id, Response::HTTP_CREATED);
    }

    /**
     * Update an existing opportunity's editable header fields.
     *
     * Closed/terminal opportunities (Complete, Cancelled, Lost, Dead) cannot be
     * edited and yield a 422.
     */
    #[ApiResponse(200, 'Opportunity updated')]
    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate(UpdateOpportunityData::rules());
        (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from($validated));

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Delete (soft-delete) an opportunity.
     *
     * Recorded as an OpportunityDeleted event; the projection row is soft-deleted
     * so it leaves list/availability reads while history is preserved.
     */
    #[ApiResponse(204, 'Opportunity deleted')]
    public function destroy(Opportunity $opportunity): JsonResponse
    {
        return $this->resourceDestroy($opportunity);
    }

    /**
     * Restore (un-archive) a soft-deleted opportunity.
     *
     * Recorded as an OpportunityRestored event; the projection row's soft-delete is
     * reversed so it re-enters list/availability reads while history is preserved.
     * The route binding resolves the trashed projection row. Restoring an
     * opportunity that is not archived is a no-op and still returns it.
     */
    #[ApiResponse(200, 'Opportunity restored')]
    public function restore(Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.delete', 'opportunities:write');

        (new RestoreOpportunity)($opportunity);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Clone an opportunity into a new Draft quotation.
     *
     * Fires a fresh OpportunityCreated (always landing as a Draft, with a freshly
     * allocated number) and replays the source's line items and costs through the
     * standard add-item / add-cost events, so the clone's demands and totals
     * rebuild naturally. The source state/status is never copied. Returns the new
     * opportunity with its items + costs.
     */
    #[ApiResponse(201, 'Opportunity cloned')]
    public function clone(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.create', 'opportunities:write');

        $result = (new CloneOpportunity)($opportunity);

        return $this->respondWithFreshOpportunity($result->id, Response::HTTP_CREATED);
    }

    /**
     * Convert a Draft opportunity into a Quotation.
     *
     * Fires the OpportunityQuoted event. Only valid from the Draft state — an
     * invalid transition yields a 422.
     */
    #[ApiResponse(200, 'Opportunity converted to quotation')]
    public function convertToQuotation(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $result = (new ConvertToQuotation)($opportunity);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Convert a Quotation into a confirmed Order.
     *
     * Fires the OpportunityConvertedToOrder event. Only valid from the Quotation
     * state — an invalid transition yields a 422. An optional `shortage_notes` is
     * recorded on the shortage acknowledgement when the confirmation gate requires
     * one (Warn policy, or a Block relaxed by the ignore permission).
     */
    #[ApiResponse(200, 'Opportunity converted to order')]
    public function convertToOrder(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate([
            'shortage_notes' => ['nullable', 'string'],
        ]);

        $result = (new ConvertToOrder)($opportunity, $validated['shortage_notes'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Release an order's FX and tax locks so it can be re-priced or re-taxed.
     *
     * Fires the OpportunityLocksReleased event, clearing `exchange_rate_locked`
     * and `tax_locked` (frozen at quote → order conversion). Requires the
     * privileged `opportunities.unlock_rates` permission; a 422 results when the
     * opportunity has no active locks to release.
     */
    #[ApiResponse(200, 'Opportunity FX/tax locks released')]
    public function unlockLocks(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.unlock_rates', 'opportunities:write');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $result = (new UnlockOpportunity)($opportunity, $validated['reason'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Apply FX and tax locks on an opportunity (freeze re-pricing / re-taxing).
     *
     * Fires the OpportunityLocksApplied event. Requires the privileged
     * `opportunities.unlock_rates` permission; a 422 results when locks are
     * already active.
     */
    #[ApiResponse(200, 'Opportunity FX/tax locks applied')]
    public function lockLocks(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.unlock_rates', 'opportunities:write');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $result = (new LockOpportunity)($opportunity, $validated['reason'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Move an opportunity to a different status within its current state.
     *
     * Fires the OpportunityStatusChanged event. The request `status` is the
     * per-state RMS integer (e.g. `1` for Quotation/Reserved); it must be a valid
     * status for the opportunity's current state or the request yields a 422.
     */
    #[ApiResponse(200, 'Opportunity status changed')]
    public function changeStatus(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate([
            'status' => ['required', 'integer'],
        ]);

        $status = OpportunityStatus::tryFrom($opportunity->state->value * 100 + (int) $validated['status']);

        if ($status === null) {
            throw ValidationException::withMessages([
                'status' => ['The given status is not valid for the opportunity\'s current state.'],
            ]);
        }

        $result = (new ChangeOpportunityStatus)($opportunity, $status);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Reinstate a lost / dead / postponed / cancelled opportunity back to an
     * active status (the backward-transition complement to the close events).
     *
     * Fires the OpportunityReinstated event via the guard pipeline. Only valid from
     * a reinstatable status (Void-phase or Held-phase) — otherwise a 422. An
     * optional `reason` is recorded on the audit trail.
     */
    #[ApiResponse(200, 'Opportunity reinstated')]
    public function reinstate(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $result = (new ReinstateOpportunity)($opportunity, $validated['reason'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Revert a confirmed Order back to a Quotation (the state-axis backward
     * transition, the inverse of convert-to-order).
     *
     * Fires the OpportunityRevertedToQuotation event via the guard pipeline. Only
     * valid from an Order with nothing dispatched — a 422 otherwise. Reverting
     * releases the order's FX/tax locks. An optional `reason` is audited.
     */
    #[ApiResponse(200, 'Opportunity reverted to quotation')]
    public function revertToQuotation(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $result = (new RevertToQuotation)($opportunity, $validated['reason'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Revert an open Quotation back to a Draft (the state-axis backward
     * transition, the inverse of convert-to-quotation; RMS `convert_to_draft`).
     *
     * Fires the OpportunityRevertedToDraft event via the guard pipeline. Only
     * valid from an open/provisional Quotation — a 422 otherwise. An optional
     * `reason` is audited.
     */
    #[ApiResponse(200, 'Opportunity reverted to draft')]
    public function revertToDraft(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $result = (new RevertToDraft)($opportunity, $validated['reason'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Re-open a terminally COMPLETE order back to an active status (RMS
     * `re_open`) — the backward-transition complement to the terminal "complete"
     * close, distinct from reinstate (which handles Void/Held closes).
     *
     * Fires the OpportunityReopened event via the guard pipeline. Only valid from
     * a completed order — a 422 otherwise. An optional `reason` is audited.
     */
    #[ApiResponse(200, 'Opportunity re-opened')]
    public function reopen(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $result = (new ReopenOpportunity)($opportunity, $validated['reason'] ?? null);

        return $this->respondWithIncludes($request, $result, $opportunity);
    }

    /**
     * Add a line item to an opportunity.
     *
     * Routes by structural `item_type`: `group` → {@see AddOpportunityGroup},
     * `accessory` → {@see AddOpportunityAccessory} (requires `principal_item_id`),
     * otherwise → {@see AddOpportunityItem} (product/service/ad-hoc). RMS catalogue
     * references may be supplied as `item_id` (mapped to `itemable_id`) plus
     * `itemable_type`. The response returns the opportunity with refreshed totals.
     */
    #[ApiResponse(201, 'Line item added')]
    public function storeItem(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $itemType = OpportunityItemType::tryFrom((string) $request->input('item_type', OpportunityItemType::Product->value))
            ?? OpportunityItemType::Product;

        match ($itemType) {
            OpportunityItemType::Group => (new AddOpportunityGroup)(
                $opportunity,
                AddOpportunityGroupData::from($request->validate(AddOpportunityGroupData::rules())),
            ),
            OpportunityItemType::Accessory => (new AddOpportunityAccessory)(
                $opportunity,
                AddOpportunityAccessoryData::from($this->mapItemableIdAlias(
                    $request->validate(array_merge(AddOpportunityAccessoryData::rules(), [
                        'item_id' => ['sometimes', 'nullable', 'integer'],
                    ])),
                )),
            ),
            default => (new AddOpportunityItem)(
                $opportunity,
                AddOpportunityItemData::from($this->mapItemableIdAlias(
                    $request->validate(array_merge(AddOpportunityItemData::rules(), [
                        'item_id' => ['sometimes', 'nullable', 'integer'],
                    ])),
                )),
            ),
        };

        return $this->respondWithFreshOpportunity($opportunity->id, Response::HTTP_CREATED);
    }

    /**
     * Restructure the opportunity's entire line-item tree.
     *
     * Body `{ nodes: [{id, depth}] }` in display pre-order; illegal placement
     * (e.g. accessory at root) yields 422 with no paths mutated.
     */
    #[ApiResponse(200, 'Line item tree restructured')]
    public function restructureItemsTree(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = RestructureOpportunityItemsData::from(
            $request->validate(RestructureOpportunityItemsData::rules()),
        );

        (new RestructureOpportunityItems)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Update a line item.
     *
     * Accepts any subset of `quantity`, `unit_price` (null clears the override),
     * `discount_percent`, `starts_at`/`ends_at`, `is_optional`, `item_id`/
     * `itemable_type`/`name` (substitution), and `name` alone (rename); each provided
     * field dispatches its own lifecycle event in turn. Tree structure is read-only
     * here — use `PATCH …/items/tree` to reorder/nest. The response returns the
     * opportunity with its refreshed totals.
     */
    #[ApiResponse(200, 'Line item updated')]
    public function updateItem(Request $request, Opportunity $opportunity, OpportunityItem $item): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);

        // A partial PATCH may dispatch several lifecycle actions in sequence (each
        // wraps its own commitVerbs transaction). Wrap the whole sequence in one
        // outer transaction so a mid-sequence failure rolls back the already-applied
        // mutations rather than leaving the line partially updated.
        DB::transaction(function () use ($request, $item): void {
            if ($request->has('quantity')) {
                (new ChangeItemQuantity)($item, ChangeItemQuantityData::from($request->validate(ChangeItemQuantityData::rules())));
            }

            if ($request->has('unit_price')) {
                (new OverrideItemPrice)($item, OverrideItemPriceData::from($request->validate(OverrideItemPriceData::rules())));
            }

            if ($request->has('discount_percent')) {
                (new SetItemDiscount)($item, SetItemDiscountData::from($request->validate(SetItemDiscountData::rules())));
            }

            if ($request->has('starts_at') || $request->has('ends_at')) {
                (new ChangeItemDates)($item, ChangeItemDatesData::from($request->validate(ChangeItemDatesData::rules())));
            }

            if ($request->has('is_optional')) {
                (new ToggleItemOptional)($item, ToggleItemOptionalData::from($request->validate(ToggleItemOptionalData::rules())));
            }

            if ($request->has('item_id') || $request->has('itemable_type')) {
                (new SubstituteItem)($item, SubstituteItemData::from($request->validate(SubstituteItemData::rules())));
            } elseif ($request->has('name')) {
                (new RenameOpportunityItem)($item, RenameOpportunityItemData::from($request->validate(RenameOpportunityItemData::rules())));
            }
        });

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Remove a line item from an opportunity.
     */
    #[ApiResponse(200, 'Line item removed')]
    public function destroyItem(Opportunity $opportunity, OpportunityItem $item): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);

        (new RemoveOpportunityItem)($item);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Allocate a serialised asset (stock level) to a line item.
     *
     * Fires the AssetAllocated genesis event: the asset is pinned to the line, the
     * stock level's allocated quantity is incremented, and the line's availability
     * demand transitions to an asset-specific demand (the bulk demand shrinks by
     * one). The asset must belong to the line's product, be serialised, and be free
     * for the line's window — otherwise a 422.
     */
    #[ApiResponse(201, 'Asset allocated')]
    public function storeAsset(Request $request, Opportunity $opportunity, OpportunityItem $item): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);

        $data = AllocateAssetData::from($request->validate(AllocateAssetData::rules()));

        $asset = (new AllocateAsset)($item, $data);

        return $this->respondWith($asset->toArray(), 'asset', Response::HTTP_CREATED);
    }

    /**
     * Mutate an existing asset assignment.
     *
     * The `action` discriminator selects the operation:
     *
     *  - allocation phase: `prepare`, `revert`, `set_container` (needs
     *    `container_stock_level_id`), `clear_container`, `substitute` (needs
     *    `new_stock_level_id`, optional `reason`);
     *  - fulfilment phase (M5-2): `dispatch` (the order must be active —
     *    `book_out`), `on_hire`, `return` (`check_in`, optional `return_store_id`),
     *    `check` (`finalise_check_in`, needs `condition`), `revert_status` (needs
     *    `revert_to`).
     *
     * An invalid status transition (e.g. dispatching an unallocated asset, or
     * dispatching on a quote) yields a 422. Dispatch/return auto-promote the parent
     * opportunity's aggregate status.
     */
    #[ApiResponse(200, 'Asset updated')]
    public function updateAsset(Request $request, Opportunity $opportunity, OpportunityItem $item, OpportunityItemAsset $asset): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);
        $this->assertAssetBelongsToItem($asset, $item);

        $action = (string) $request->validate([
            'action' => ['required', 'string', 'in:prepare,revert,set_container,clear_container,substitute,dispatch,on_hire,return,check,revert_status'],
        ])['action'];

        // The dispatch action runs the §7.4 shortage gate and may hold back items
        // under a WarnPartial store policy — capture it so its held-item metadata
        // can be surfaced on the response.
        if ($action === 'dispatch') {
            $dispatch = new DispatchAsset;
            $result = $dispatch($asset, DispatchAssetData::from($request->validate(DispatchAssetData::rules())));

            return $this->respondWithDispatchMeta($result->toArray(), 'asset', $dispatch->gateResult);
        }

        $result = match ($action) {
            'prepare' => (new PrepareAsset)($asset),
            'revert' => (new RevertAssetPreparation)($asset),
            'set_container' => (new SetAssetContainer)($asset, SetAssetContainerData::from($request->validate(SetAssetContainerData::rules()))),
            'clear_container' => (new ClearAssetContainer)($asset),
            'substitute' => (new SubstituteAsset)($asset, SubstituteAssetData::from($request->validate(SubstituteAssetData::rules()))),
            'on_hire' => (new MarkAssetOnHire)($asset),
            'return' => (new ReturnAsset)($asset, ReturnAssetData::from($request->validate(ReturnAssetData::rules()))),
            'check' => (new CheckAsset)($asset, CheckAssetData::from($request->validate(CheckAssetData::rules()))),
            'revert_status' => (new RevertAssetStatus)($asset, RevertAssetStatusData::from($request->validate(RevertAssetStatusData::rules()))),
            default => throw ValidationException::withMessages(['action' => ['The selected action is invalid.']]),
        };

        return $this->respondWith($result->toArray(), 'asset');
    }

    /**
     * Dispatch, return, or adjust a bulk (non-serialised) line's quantity (M5-2).
     *
     * The `action` discriminator selects the operation: `dispatch` (needs
     * `quantity`), `return` (needs `quantity`, optional `condition`), or `adjust`
     * (needs `new_quantity`). Over-dispatch / over-return and adjusting below the
     * dispatched quantity yield a 422.
     */
    #[ApiResponse(200, 'Bulk quantity updated')]
    public function updateBulkQuantity(Request $request, Opportunity $opportunity, OpportunityItem $item): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);

        $action = (string) $request->validate([
            'action' => ['required', 'string', 'in:dispatch,return,adjust'],
        ])['action'];

        // The dispatch action runs the §7.4 shortage gate; capture it so a
        // WarnPartial held-item set can be surfaced on the response.
        if ($action === 'dispatch') {
            $dispatch = new DispatchBulkQuantity;
            $result = $dispatch($item, BulkDispatchData::from($request->validate(BulkDispatchData::rules())));

            return $this->respondWithDispatchMeta($result->toArray(), 'item', $dispatch->gateResult);
        }

        $result = match ($action) {
            'return' => (new ReturnBulkQuantity)($item, BulkReturnData::from($request->validate(BulkReturnData::rules()))),
            'adjust' => (new AdjustBulkQuantity)($item, BulkAdjustData::from($request->validate(BulkAdjustData::rules()))),
            default => throw ValidationException::withMessages(['action' => ['The selected action is invalid.']]),
        };

        return $this->respondWith($result->toArray(), 'item');
    }

    /**
     * Book several serialised assets out of an opportunity in one atomic commit
     * (the RMS `quick_book_out` action). Every dispatch fires inside a single Verbs
     * commit, so a failure on any one rolls back the batch and the order's aggregate
     * status promotes once consistently.
     */
    #[ApiResponse(200, 'Assets booked out')]
    public function quickBookOut(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = QuickBookOutData::from($request->validate(QuickBookOutData::rules()));

        // The batch dispatch runs the §7.4 shortage gate across the batch's lines;
        // capture it so a WarnPartial held-item set is surfaced on the response.
        $bookOut = new QuickBookOut;
        $bookOut($opportunity, $data);

        return $this->respondWithDispatchMeta(
            OpportunityData::fromModel(
                Opportunity::query()->whereKey($opportunity->id)->with(['items', 'costs', 'customFieldValues'])->firstOrFail()
            )->toArray(),
            'opportunity',
            $bookOut->gateResult,
        );
    }

    /**
     * Check several serialised assets back into an opportunity in one atomic commit
     * (the RMS `quick_check_in` action). With `finalise` each return is immediately
     * condition-checked (Good). Atomic: a failure on any one rolls back the batch.
     */
    #[ApiResponse(200, 'Assets checked in')]
    public function quickCheckIn(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = QuickCheckInData::from($request->validate(QuickCheckInData::rules()));

        (new QuickCheckIn)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Deallocate (release) an asset from its line item.
     *
     * Fires AssetDeallocated: the assignment row is removed, the stock level's
     * allocated quantity is decremented, and the freed unit reverts to a
     * quantity-based demand. Only allowed while the asset is Allocated or Prepared.
     */
    #[ApiResponse(204, 'Asset deallocated')]
    public function destroyAsset(Request $request, Opportunity $opportunity, OpportunityItem $item, OpportunityItemAsset $asset): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);
        $this->assertAssetBelongsToItem($asset, $item);

        $reason = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:255']])['reason'] ?? null;

        (new DeallocateAsset)($asset, $reason);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Batch-allocate several serialised assets to line items in one atomic commit
     * (the RMS `quick_allocate` action).
     *
     * Every allocation fires inside a single Verbs commit, so a failure on any one
     * (asset unavailable, wrong product) rolls back the whole batch. All allocations
     * must target line items of the bound opportunity. Returns the opportunity with
     * its items + assets.
     */
    #[ApiResponse(200, 'Assets allocated')]
    public function quickAllocate(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = QuickAllocateAssetsData::from($request->validate(QuickAllocateAssetsData::rules()));

        (new QuickAllocateAssets)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Batch-prepare several allocated assets in one atomic commit (the RMS
     * `quick_prepare` action).
     *
     * Every preparation fires inside a single Verbs commit, so a failure on any one
     * (an asset not in the Allocated status) rolls back the whole batch. All assets
     * must belong to the bound opportunity. Returns the opportunity with its items +
     * assets.
     */
    #[ApiResponse(200, 'Assets prepared')]
    public function quickPrepare(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = QuickPrepareAssetsData::from($request->validate(QuickPrepareAssetsData::rules()));

        (new QuickPrepareAssets)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Add an ad-hoc cost (delivery, labour, surcharge, etc.) to an opportunity.
     *
     * The cost is taxed (matching the line-item inclusive/exclusive handling) and
     * the opportunity totals are recomputed — its net is routed into the transit /
     * loss-damage / service bucket by cost type. The response returns the
     * opportunity with its refreshed totals.
     */
    #[ApiResponse(201, 'Cost added')]
    public function storeCost(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = AddOpportunityCostData::from($request->validate(AddOpportunityCostData::rules()));

        (new AddOpportunityCost)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id, Response::HTTP_CREATED);
    }

    /**
     * Update an opportunity cost.
     *
     * Accepts any subset of `description`, `cost_type`, `transaction_type`,
     * `amount`, `quantity`, `is_optional`, `sort_order`, and `notes`; omitted
     * fields are left untouched. The response returns the opportunity with its
     * refreshed totals.
     */
    #[ApiResponse(200, 'Cost updated')]
    public function updateCost(Request $request, Opportunity $opportunity, OpportunityCost $cost): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertCostBelongsToOpportunity($cost, $opportunity);

        $data = UpdateOpportunityCostData::from($request->validate(UpdateOpportunityCostData::rules()));

        (new UpdateOpportunityCost)($cost, $data);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Remove an ad-hoc cost from an opportunity.
     */
    #[ApiResponse(200, 'Cost removed')]
    public function destroyCost(Opportunity $opportunity, OpportunityCost $cost): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertCostBelongsToOpportunity($cost, $opportunity);

        (new RemoveOpportunityCost)($cost);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Attach a member to an opportunity in a named role (RMS `participants[]`).
     *
     * Participants are plain, non-event-sourced CRM associations. A member may be
     * attached only once per opportunity. The response returns the created
     * participant with its member reference.
     */
    #[ApiResponse(201, 'Participant added')]
    public function storeParticipant(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = AddOpportunityParticipantData::from($request->validate(AddOpportunityParticipantData::rules()));

        $participant = (new AddOpportunityParticipant)($opportunity, $data);

        return response()->json(['participant' => $participant->toArray()], Response::HTTP_CREATED);
    }

    /**
     * Update a participant's role and/or mute flag.
     *
     * Accepts any subset of `role` and `mute`; omitted fields are left untouched.
     * The member association is immutable — to change it, remove and re-add the
     * participant.
     */
    #[ApiResponse(200, 'Participant updated')]
    public function updateParticipant(Request $request, Opportunity $opportunity, OpportunityParticipant $participant): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertParticipantBelongsToOpportunity($participant, $opportunity);

        $data = UpdateOpportunityParticipantData::from($request->validate(UpdateOpportunityParticipantData::rules()));

        $updated = (new UpdateOpportunityParticipant)($participant, $data);

        return response()->json(['participant' => $updated->toArray()]);
    }

    /**
     * Detach a member from an opportunity.
     */
    #[ApiResponse(204, 'Participant removed')]
    public function destroyParticipant(Opportunity $opportunity, OpportunityParticipant $participant): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertParticipantBelongsToOpportunity($participant, $opportunity);

        (new RemoveOpportunityParticipant)($participant);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Set a manual deal-total override on an opportunity, replacing the
     * engine-computed headline `charge_total`.
     */
    #[ApiResponse(200, 'Deal price set')]
    public function setDealPrice(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = SetDealPriceData::from($request->validate(SetDealPriceData::rules()));

        (new SetDealPrice)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Clear a manual deal-total override, reverting `charge_total` to the
     * engine-computed gross total.
     */
    #[ApiResponse(200, 'Deal price cleared')]
    public function clearDealPrice(Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        (new ClearDealPrice)($opportunity);

        return $this->respondWithFreshOpportunity($opportunity->id);
    }

    /**
     * Map RMS `item_id` to the internal `itemable_id` when the latter is omitted.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapItemableIdAlias(array $validated): array
    {
        if (array_key_exists('item_id', $validated) && ! array_key_exists('itemable_id', $validated)) {
            $validated['itemable_id'] = $validated['item_id'];
        }

        return $validated;
    }

    /**
     * Guard that a line item belongs to the bound opportunity (else 404).
     */
    private function assertItemBelongsToOpportunity(OpportunityItem $item, Opportunity $opportunity): void
    {
        abort_unless($item->opportunity_id === $opportunity->id, Response::HTTP_NOT_FOUND);
    }

    /**
     * Guard that a cost belongs to the bound opportunity (else 404).
     */
    private function assertCostBelongsToOpportunity(OpportunityCost $cost, Opportunity $opportunity): void
    {
        abort_unless($cost->opportunity_id === $opportunity->id, Response::HTTP_NOT_FOUND);
    }

    /**
     * Guard that a participant belongs to the bound opportunity (else 404).
     */
    private function assertParticipantBelongsToOpportunity(OpportunityParticipant $participant, Opportunity $opportunity): void
    {
        abort_unless($participant->opportunity_id === $opportunity->id, Response::HTTP_NOT_FOUND);
    }

    /**
     * Guard that an asset assignment belongs to the bound line item (else 404).
     */
    private function assertAssetBelongsToItem(OpportunityItemAsset $asset, OpportunityItem $item): void
    {
        abort_unless($asset->opportunity_item_id === $item->id, Response::HTTP_NOT_FOUND);
    }

    /**
     * Describe one available action for the `available_actions` endpoint: combine
     * a generic STATE precondition (the Verbs `validate()` invariant the pipeline
     * does not run) with the non-throwing guard-pipeline {@see GuardPipeline::check()}
     * and a direct permission probe, producing the `{key, label, allowed, reason,
     * code}` shape. The pipeline check only runs when the state precondition and the
     * permission pass, so its shortage/FX-lock prechecks reflect a genuinely
     * reachable transition.
     *
     * @param  Closure(): (array{0: string, 1: string}|null)|null  $statePrecondition  Returns a `[message, code]` denial or null to pass.
     * @return array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}
     */
    private function describeAction(
        Opportunity $opportunity,
        string $key,
        string $label,
        string $permission,
        ?string $transition,
        ?Closure $statePrecondition = null,
    ): array {
        return app(OpportunityActionDescriber::class)
            ->describe($opportunity, $key, $label, $permission, $transition, $statePrecondition);
    }

    /**
     * The revert-to-quotation state precondition: must be an open Order with no
     * dispatch history. Returns a `[message, code]` denial or null to pass.
     *
     * @return array{0: string, 1: string}|null
     */
    private function revertToQuotationPrecondition(Opportunity $opportunity, bool $isOrder, bool $isClosed): ?array
    {
        return app(OpportunityActionDescriber::class)
            ->revertToQuotationPrecondition($opportunity, $isOrder, $isClosed);
    }

    /**
     * Respond with a dispatch result, merging the §7.4 dispatch-gate held-item
     * metadata under `_meta` when a WarnPartial store policy held items back. A
     * Block already threw before reaching here; AllowPartial / no-shortage add no
     * meta (the held-items array is empty).
     *
     * @param  array<string, mixed>  $data
     */
    private function respondWithDispatchMeta(array $data, string $key, ?DispatchGateResult $gateResult): JsonResponse
    {
        $payload = [$key => $data];

        $meta = $gateResult?->toHeldItemsMeta() ?? [];

        if ($meta !== []) {
            $payload['_meta'] = $meta;
        }

        return response()->json($payload);
    }

    /**
     * Re-read the opportunity projection with its items + costs + custom field
     * values and serialise it. `customFieldValues` MUST be eager-loaded here so
     * mutation responses return the populated `custom_fields` object rather than
     * an empty `{}` (OpportunityData only emits custom fields when the relation
     * is loaded).
     */
    private function respondWithFreshOpportunity(int $opportunityId, int $status = Response::HTTP_OK): JsonResponse
    {
        $fresh = Opportunity::query()->whereKey($opportunityId)->with(['items', 'costs', 'customFieldValues'])->firstOrFail();

        return $this->respondWithOpportunityMeta(
            OpportunityData::fromModel($fresh)->toArray(),
            $fresh,
            $status,
        );
    }

    /**
     * Wrap a serialised opportunity under the `opportunity` key and attach the RMS
     * `meta` block — `{can_edit, can_destroy}` resolved from the opportunity policy
     * for the current actor — as a sibling of the resource. A closed/terminal
     * opportunity reports `can_edit = false` so a consumer can hide edit controls
     * without re-deriving the lifecycle rules.
     *
     * @param  array<string, mixed>  $data
     */
    private function respondWithOpportunityMeta(array $data, Opportunity $opportunity, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'opportunity' => $data,
            'meta' => [
                'can_edit' => Gate::allows('update', $opportunity) && ! $opportunity->statusEnum()->isClosed(),
                'can_destroy' => Gate::allows('delete', $opportunity),
            ],
        ], $status);
    }

    /**
     * Re-serialise the action result with any requested `?include=` relationships
     * applied to the refreshed projection row.
     */
    private function respondWithIncludes(Request $request, OpportunityData $result, Opportunity $opportunity): JsonResponse
    {
        $fresh = Opportunity::query()->whereKey($result->id)->firstOrFail();
        $this->applyIncludes(Opportunity::query(), $request, $fresh);

        return $this->respondWithOpportunityMeta(
            OpportunityData::fromModel($fresh)->toArray(),
            $fresh,
        );
    }

    /**
     * Filter a response array to only include the view's column fields + id.
     *
     * Custom field columns (cf.*) filter the custom_fields sub-array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterResponseByView(array $data, CustomView $view): array
    {
        $viewColumns = $view->columns;
        $allowedKeys = ['id'];
        $allowedCustomFieldKeys = [];

        foreach ($viewColumns as $column) {
            if (str_starts_with($column, 'cf.')) {
                $allowedCustomFieldKeys[] = substr($column, 3);
            } else {
                $allowedKeys[] = $column;
            }
        }

        $filtered = array_intersect_key($data, array_flip($allowedKeys));

        if (! empty($allowedCustomFieldKeys) && isset($data['custom_fields'])) {
            $filtered['custom_fields'] = (object) array_intersect_key(
                (array) $data['custom_fields'],
                array_flip($allowedCustomFieldKeys),
            );
        }

        return $filtered;
    }
}
