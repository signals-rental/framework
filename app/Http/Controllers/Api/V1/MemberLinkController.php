<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\CreateLink;
use App\Actions\Members\DeleteLink;
use App\Actions\Members\UpdateLink;
use App\Data\Members\CreateLinkData;
use App\Data\Members\LinkData;
use App\Data\Members\UpdateLinkData;
use App\Http\Controllers\Api\Controller;
use App\Models\Link;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberLinkController extends Controller
{
    /**
     * List links for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $links = $member->links->map(
            fn (Link $link): array => LinkData::fromModel($link)->toArray()
        )->all();

        return $this->respondWithCollection($links, 'links');
    }

    /**
     * Create a link for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateLinkData::rules());
        $dto = CreateLinkData::from($validated);

        $result = (new CreateLink)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'link',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a link.
     */
    public function update(Request $request, Member $member, Link $link): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdateLinkData::rules());
        $dto = UpdateLinkData::from($validated);

        $result = (new UpdateLink)($link, $dto);

        return $this->respondWith(
            $result->toArray(),
            'link',
        );
    }

    /**
     * Delete a link.
     */
    public function destroy(Member $member, Link $link): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteLink)($link);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
