<?php

namespace App\Actions\Attachments;

use App\Data\Attachments\AttachmentData;
use App\Data\Attachments\CreateAttachmentData;
use App\Models\Member;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class CreateAttachment
{
    /** @var array<string, class-string<Model>> */
    private array $morphMap = [
        'Member' => Member::class,
    ];

    public function __invoke(CreateAttachmentData $data, UploadedFile $file): AttachmentData
    {
        $modelClass = $this->morphMap[$data->attachable_type]
            ?? throw new \InvalidArgumentException("Unknown attachable type: {$data->attachable_type}");

        $entity = $modelClass::findOrFail($data->attachable_id);

        Gate::authorize('view', $entity);

        $fileService = app(FileService::class);
        $attachment = $fileService->upload($file, $entity, $data->category, $data->description);

        return AttachmentData::fromModel($attachment);
    }
}
