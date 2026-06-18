<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\CustomView;
use App\Models\Opportunity;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
     * state — an invalid transition yields a 422.
     */
    #[ApiResponse(200, 'Opportunity converted to order')]
    public function convertToOrder(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $result = (new ConvertToOrder)($opportunity);

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
