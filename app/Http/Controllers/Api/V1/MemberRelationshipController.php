<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\CreateMemberRelationship;
use App\Actions\Members\DeleteMemberRelationship;
use App\Data\Members\CreateMemberRelationshipData;
use App\Data\Members\MemberRelationshipData;
use App\Http\Controllers\Api\Controller;
use App\Models\Member;
use App\Models\MemberRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberRelationshipController extends Controller
{
    /**
     * List relationships for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $relationships = $member->memberRelationships()
            ->with('relatedMember')
            ->get()
            ->map(fn (MemberRelationship $rel): array => MemberRelationshipData::fromModel($rel)->toArray())
            ->all();

        return $this->respondWithCollection($relationships, 'relationships');
    }

    /**
     * Create a relationship for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateMemberRelationshipData::rules());
        $dto = CreateMemberRelationshipData::from($validated);

        $result = (new CreateMemberRelationship)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'relationship',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Delete a relationship.
     */
    public function destroy(Member $member, MemberRelationship $relationship): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteMemberRelationship)($relationship);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
