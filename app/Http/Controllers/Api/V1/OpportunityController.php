<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityCost;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SubstituteItem;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Actions\Opportunities\UpdateOpportunityCost;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SubstituteItemData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\CustomView;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        'starts_at',
        'ends_at',
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
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'member',
        'venue',
        'store',
        'owner',
        'items',
        'items.assets',
        'costs',
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
        return $this->resourceShow($request, $opportunity);
    }

    /**
     * Create a new opportunity.
     *
     * The opportunity is created as a Draft via the OpportunityCreated event.
     */
    #[ApiResponse(201, 'Opportunity created')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
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
        return $this->resourceUpdate($request, $opportunity);
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
     * Add a line item to an opportunity.
     *
     * The item is priced by the rate + tax engines and the opportunity totals are
     * recomputed; the response returns the opportunity with its refreshed totals.
     */
    #[ApiResponse(201, 'Line item added')]
    public function storeItem(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = AddOpportunityItemData::from($request->validate(AddOpportunityItemData::rules()));

        (new AddOpportunityItem)($opportunity, $data);

        return $this->respondWithFreshOpportunity($opportunity->id, Response::HTTP_CREATED);
    }

    /**
     * Update a line item.
     *
     * Accepts any subset of `quantity`, `unit_price` (null clears the override),
     * `discount_percent`, `starts_at`/`ends_at`, `is_optional`, and `item_id`/
     * `item_type`/`name` (substitution); each provided field dispatches its own
     * lifecycle event in turn. The response returns the opportunity with its
     * refreshed totals.
     */
    #[ApiResponse(200, 'Line item updated')]
    public function updateItem(Request $request, Opportunity $opportunity, OpportunityItem $item): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $this->assertItemBelongsToOpportunity($item, $opportunity);

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

        if ($request->has('item_id') || $request->has('item_type')) {
            (new SubstituteItem)($item, SubstituteItemData::from($request->validate(SubstituteItemData::rules())));
        }

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
     * Re-read the opportunity projection with its items + costs and serialise it.
     */
    private function respondWithFreshOpportunity(int $opportunityId, int $status = Response::HTTP_OK): JsonResponse
    {
        $fresh = Opportunity::query()->whereKey($opportunityId)->with(['items', 'costs'])->firstOrFail();

        return $this->respondWith(
            OpportunityData::fromModel($fresh)->toArray(),
            'opportunity',
            $status,
        );
    }

    /**
     * Re-serialise the action result with any requested `?include=` relationships
     * applied to the refreshed projection row.
     */
    private function respondWithIncludes(Request $request, OpportunityData $result, Opportunity $opportunity): JsonResponse
    {
        $fresh = Opportunity::query()->whereKey($result->id)->firstOrFail();
        $this->applyIncludes(Opportunity::query(), $request, $fresh);

        return $this->respondWith(
            OpportunityData::fromModel($fresh)->toArray(),
            'opportunity',
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
