<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\CreateEmail;
use App\Actions\Members\DeleteEmail;
use App\Actions\Members\UpdateEmail;
use App\Data\Members\CreateEmailData;
use App\Data\Members\EmailData;
use App\Data\Members\UpdateEmailData;
use App\Http\Controllers\Api\Controller;
use App\Models\Email;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberEmailController extends Controller
{
    /**
     * List emails for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $emails = $member->emails->map(
            fn (Email $email): array => EmailData::fromModel($email)->toArray()
        )->all();

        return $this->respondWithCollection($emails, 'emails');
    }

    /**
     * Create an email for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateEmailData::rules());
        $dto = CreateEmailData::from($validated);

        $result = (new CreateEmail)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'email',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an email.
     */
    public function update(Request $request, Member $member, Email $email): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdateEmailData::rules());
        $dto = UpdateEmailData::from($validated);

        $result = (new UpdateEmail)($email, $dto);

        return $this->respondWith(
            $result->toArray(),
            'email',
        );
    }

    /**
     * Delete an email.
     */
    public function destroy(Member $member, Email $email): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteEmail)($email);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
