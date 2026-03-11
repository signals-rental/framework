<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\CreateAddress;
use App\Actions\Members\DeleteAddress;
use App\Actions\Members\UpdateAddress;
use App\Data\Members\AddressData;
use App\Data\Members\CreateAddressData;
use App\Data\Members\UpdateAddressData;
use App\Http\Controllers\Api\Controller;
use App\Models\Address;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberAddressController extends Controller
{
    /**
     * List addresses for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $addresses = $member->addresses->map(
            fn (Address $address): array => AddressData::fromModel($address)->toArray()
        )->all();

        return $this->respondWithCollection($addresses, 'addresses');
    }

    /**
     * Create an address for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateAddressData::rules());
        $dto = CreateAddressData::from($validated);

        $result = (new CreateAddress)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'address',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an address.
     */
    public function update(Request $request, Member $member, Address $address): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdateAddressData::rules());
        $dto = UpdateAddressData::from($validated);

        $result = (new UpdateAddress)($address, $dto);

        return $this->respondWith(
            $result->toArray(),
            'address',
        );
    }

    /**
     * Delete an address.
     */
    public function destroy(Member $member, Address $address): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteAddress)($address);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
