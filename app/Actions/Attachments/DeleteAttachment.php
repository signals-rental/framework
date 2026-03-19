<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use App\Services\FileService;
use Illuminate\Support\Facades\Gate;

class DeleteAttachment
{
    public function __invoke(Attachment $attachment): void
    {
        Gate::authorize('delete', $attachment);

        app(FileService::class)->delete($attachment);
    }
}
