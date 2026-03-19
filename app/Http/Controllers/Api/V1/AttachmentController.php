<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attachments\CreateAttachment;
use App\Actions\Attachments\DeleteAttachment;
use App\Data\Attachments\AttachmentData;
use App\Data\Attachments\CreateAttachmentData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Attachment;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'mime_type',
        'category',
        'original_name',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'created_at',
        'original_name',
        'file_size',
    ];

    /**
     * List attachments for a member.
     *
     * @response array{attachments: list<AttachmentData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function indexForMember(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $query = $member->attachments()->getQuery();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $attachments = $paginator->getCollection()->map(
            fn (Attachment $attachment): array => AttachmentData::fromModel($attachment)->toArray()
        )->all();

        return $this->respondWithCollection($attachments, 'attachments', $paginator);
    }

    /**
     * Show a single attachment.
     *
     * @response array{attachment: AttachmentData}
     */
    public function show(Attachment $attachment): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        return $this->respondWith(
            AttachmentData::fromModel($attachment)->toArray(),
            'attachment',
        );
    }

    /**
     * Upload a new attachment.
     *
     * @response 201 array{attachment: AttachmentData}
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateAttachmentData::rules());
        $dto = CreateAttachmentData::from($validated);

        $file = $request->file('file');
        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            return $this->respondWithError('File upload failed. The file may exceed server size limits.', 422);
        }

        $result = (new CreateAttachment)($dto, $file);

        return $this->respondWith(
            $result->toArray(),
            'attachment',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteAttachment)($attachment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
