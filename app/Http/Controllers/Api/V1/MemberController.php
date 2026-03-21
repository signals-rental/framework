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
use App\Http\Traits\ResourceActions;
use App\Models\CustomView;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use FiltersQueries, ResourceActions;

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

    protected function modelClass(): string
    {
        return Member::class;
    }

    protected function responseDataClass(): string
    {
        return MemberData::class;
    }

    protected function createDataClass(): string
    {
        return CreateMemberData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateMemberData::class;
    }

    protected function createActionClass(): string
    {
        return CreateMember::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateMember::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteMember::class;
    }

    protected function singularKey(): string
    {
        return 'member';
    }

    protected function pluralKey(): string
    {
        return 'members';
    }

    protected function entityType(): string
    {
        return 'members';
    }

    protected function permissions(): array
    {
        return ['view' => 'members.view', 'create' => 'members.create', 'edit' => 'members.edit', 'delete' => 'members.delete'];
    }

    protected function abilities(): array
    {
        return ['read' => 'members:read', 'write' => 'members:write'];
    }

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

        ['query' => $query, 'view' => $view] = $this->applyViewOrFilters($query, $request, 'members');

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
        return $this->resourceShow($request, $member);
    }

    /**
     * Create a new member.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing member.
     */
    public function update(Request $request, Member $member): JsonResponse
    {
        return $this->resourceUpdate($request, $member);
    }

    /**
     * Delete (soft-delete) a member.
     */
    public function destroy(Member $member): JsonResponse
    {
        return $this->resourceDestroy($member);
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
