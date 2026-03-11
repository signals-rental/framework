<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\CreatePhone;
use App\Actions\Members\DeletePhone;
use App\Actions\Members\UpdatePhone;
use App\Data\Members\CreatePhoneData;
use App\Data\Members\PhoneData;
use App\Data\Members\UpdatePhoneData;
use App\Http\Controllers\Api\Controller;
use App\Models\Member;
use App\Models\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberPhoneController extends Controller
{
    /**
     * List phones for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $phones = $member->phones->map(
            fn (Phone $phone): array => PhoneData::fromModel($phone)->toArray()
        )->all();

        return $this->respondWithCollection($phones, 'phones');
    }

    /**
     * Create a phone for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreatePhoneData::rules());
        $dto = CreatePhoneData::from($validated);

        $result = (new CreatePhone)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'phone',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a phone.
     */
    public function update(Request $request, Member $member, Phone $phone): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdatePhoneData::rules());
        $dto = UpdatePhoneData::from($validated);

        $result = (new UpdatePhone)($phone, $dto);

        return $this->respondWith(
            $result->toArray(),
            'phone',
        );
    }

    /**
     * Delete a phone.
     */
    public function destroy(Member $member, Phone $phone): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeletePhone)($phone);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
