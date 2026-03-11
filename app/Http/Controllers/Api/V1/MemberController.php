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
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'membership_type',
        'created_at',
        'updated_at',
    ];

    /**
     * List members with filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $query = Member::query();
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $members = $paginator->getCollection()->map(
            fn (Member $member): array => MemberData::fromModel($member)->toArray()
        )->all();

        return $this->respondWithCollection($members, 'members', $paginator);
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
     * Apply ?include= eager loading for member relationships.
     */
    private function applyIncludes(
        \Illuminate\Database\Eloquent\Builder $query,
        Request $request,
        ?Member $member = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $includes = array_filter(explode(',', $request->input('include', '')));

        $allowedIncludes = ['addresses', 'emails', 'phones', 'links', 'customFieldValues'];
        $eagerLoad = array_intersect($includes, $allowedIncludes);

        if ($member) {
            $member->load($eagerLoad);
        }

        if (! empty($eagerLoad)) {
            $query->with($eagerLoad);
        }

        return $query;
    }
}
