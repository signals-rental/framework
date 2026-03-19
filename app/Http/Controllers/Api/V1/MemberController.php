<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\CreateMember;
use App\Actions\Members\DeleteMember;
use App\Actions\Members\UpdateMember;
use App\Data\Members\CreateMemberData;
use App\Data\Members\MemberData;
use App\Data\Members\UpdateMemberData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\CustomView;
use App\Models\Member;
use App\Services\ViewResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'membership_type',
        'is_active',
        'created_at',
    ];

    protected string $customFieldModule = 'Member';

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'membership_type',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'addresses',
        'emails',
        'phones',
        'links',
        'customFieldValues',
        'saleTaxClass',
        'purchaseTaxClass',
        'lawfulBasisType',
        'contacts',
        'organisations',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'customFieldValues',
        'saleTaxClass',
        'purchaseTaxClass',
        'lawfulBasisType',
    ];

    /**
     * List members with filtering, sorting, and pagination.
     *
     * Supports `view_id` query parameter to apply a saved custom view.
     * View filters merge with explicit `q` params (explicit params take priority).
     * View sort applies only when no explicit `sort` param is given.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $query = Member::query();
        $query = $this->applyIncludes($query, $request);

        // Resolve view if requested
        $viewId = $request->filled('view_id') ? (int) $request->input('view_id') : null;
        $viewResolver = app(ViewResolver::class);
        $view = $viewResolver->resolve('members', $viewId, $request->user());

        if ($view !== null) {
            // Apply view filters, merging with explicit request filters
            $explicitFilters = $request->input('q', []);
            if (! is_array($explicitFilters)) {
                $explicitFilters = [];
            }
            $query = $viewResolver->applyFilters($query, $view, $explicitFilters);

            // Apply view sort only if no explicit sort given
            if (! $request->filled('sort')) {
                $query = $viewResolver->applySort($query, $view);
            } else {
                $query = $this->applySort($query, $request);
            }
        } else {
            $query = $this->applyFilters($query, $request);
            $query = $this->applySort($query, $request);
        }

        /** @var LengthAwarePaginator<int, Member> $paginator */
        $paginator = $this->paginateQuery($query, $request);

        $members = $paginator->getCollection()->map(
            fn (Member $member): array => $view !== null
                ? $this->filterResponseByView(MemberData::fromModel($member)->toArray(), $view)
                : MemberData::fromModel($member)->toArray()
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
            'members' => $members,
            'meta' => $meta,
        ]);
    }

    /**
     * Show a single member.
     */
    public function show(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $this->applyIncludes(Member::query(), $request, $member);

        return $this->respondWith(
            MemberData::fromModel($member)->toArray(),
            'member',
        );
    }

    /**
     * Create a new member.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('members.create', 'members:write');

        $validated = $request->validate(CreateMemberData::rules());
        $dto = CreateMemberData::from($validated);

        $result = (new CreateMember)($dto);

        return $this->respondWith(
            $result->toArray(),
            'member',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing member.
     */
    public function update(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdateMemberData::rules());
        $dto = UpdateMemberData::from($validated);

        $result = (new UpdateMember)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'member',
        );
    }

    /**
     * Delete (soft-delete) a member.
     */
    public function destroy(Member $member): JsonResponse
    {
        $this->authorizeApi('members.delete', 'members:write');

        (new DeleteMember)($member);

        return response()->json(null, Response::HTTP_NO_CONTENT);
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
            $filtered['custom_fields'] = array_intersect_key(
                $data['custom_fields'],
                array_flip($allowedCustomFieldKeys),
            );
        }

        return $filtered;
    }
}
