<?php

namespace App\Data\Attachments;

use App\Models\Attachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\Data;

class AttachmentData extends Data
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $original_name,
        public string $mime_type,
        public int $file_size,
        public ?string $category,
        public ?string $description,
        public string $url,
        public ?string $thumb_url,
        public ?int $uploaded_by,
        public string $created_at,
        public string $updated_at,
    ) {}

    /**
     * Generate a displayable URL for a storage path.
     *
     * Returns a signed temporary URL for S3, or a plain URL for local disks.
     */
    private static function resolveUrl(string $path, string $disk): string
    {
        try {
            if ($disk === 'local' || $disk === 'public') {
                return Storage::disk($disk)->url($path);
            }

            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(60));
        } catch (\Throwable) {
            return $path;
        }
    }

    public static function fromModel(Attachment $attachment): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $attachment->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $attachment->updated_at;

        return new self(
            id: $attachment->id,
            uuid: $attachment->uuid,
            original_name: $attachment->original_name,
            mime_type: $attachment->mime_type,
            file_size: $attachment->file_size,
            category: $attachment->category,
            description: $attachment->description,
            url: self::resolveUrl($attachment->file_path, $attachment->disk),
            thumb_url: $attachment->thumb_path ? self::resolveUrl($attachment->thumb_path, $attachment->disk) : null,
            uploaded_by: $attachment->uploaded_by,
            created_at: $createdAt->utc()->toIso8601String(),
            updated_at: $updatedAt->utc()->toIso8601String(),
        );
    }
}
