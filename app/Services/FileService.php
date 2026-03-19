<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class FileService
{
    /**
     * Get the storage disk name for file operations.
     *
     * Uses S3 in production. Falls back to `public` disk for local development
     * so files are accessible via the `/storage` symlink.
     */
    private function disk(): string
    {
        $disk = config('filesystems.default', 'local');

        // The 'local' disk stores in storage/app/ which isn't web-accessible.
        // Use 'public' disk instead so icons/attachments can be served via /storage.
        if ($disk === 'local') {
            return 'public';
        }

        return $disk;
    }

    /**
     * Upload a file and create an attachment record.
     */
    public function upload(
        UploadedFile $file,
        Model $entity,
        ?string $category = null,
        ?string $description = null,
    ): Attachment {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $path = $this->storePath($uuid, $extension);
        $disk = $this->disk();

        Storage::disk($disk)->put($path, $file->getContent());

        return Attachment::create([
            'uuid' => $uuid,
            'attachable_type' => $entity->getMorphClass(),
            'attachable_id' => $entity->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $category,
            'description' => $description,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Upload an icon image and generate a 150x150 thumbnail.
     *
     * @return array{icon_url: string, icon_thumb_url: string}
     */
    public function uploadIcon(UploadedFile $file, Model $entity): array
    {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension() ?: 'jpg';

        $entityDir = strtolower(class_basename($entity)).'s/'.$entity->getKey();
        $iconPath = "icons/{$entityDir}/{$uuid}.{$extension}";
        $thumbPath = "icons/{$entityDir}/thumbs/{$uuid}.{$extension}";
        $disk = $this->disk();

        // Store original
        Storage::disk($disk)->put($iconPath, $file->getContent());

        // Generate and store 400x400 thumbnail (preserves quality for profile display)
        $thumbnail = $this->generateThumbnail($file, 400, 400);
        Storage::disk($disk)->put($thumbPath, $thumbnail);

        return [
            'icon_url' => $iconPath,
            'icon_thumb_url' => $thumbPath,
        ];
    }

    /**
     * Delete an attachment from storage and database.
     */
    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->file_path);

        if ($attachment->thumb_path) {
            Storage::disk($attachment->disk)->delete($attachment->thumb_path);
        }

        $attachment->delete();
    }

    /**
     * Generate a signed temporary URL for a file path.
     */
    public function signedUrl(string $path, int $expiry = 60): string
    {
        $disk = $this->disk();

        if ($disk === 'local' || $disk === 'public') {
            return Storage::disk($disk)->url($path);
        }

        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($expiry));
    }

    /**
     * Generate a thumbnail image using Intervention Image.
     */
    private function generateThumbnail(UploadedFile $file, int $width, int $height): string
    {
        $image = Image::read($file->getContent());
        $image->cover($width, $height);

        return $image->toJpeg(quality: 92)->toString();
    }

    /**
     * Generate a UUID-based storage path.
     */
    private function storePath(string $uuid, string $extension): string
    {
        return "attachments/{$uuid}.{$extension}";
    }
}
