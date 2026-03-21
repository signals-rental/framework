<?php

namespace App\Livewire\Concerns;

use App\Models\Attachment;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Shared file management actions for entity files sub-pages.
 *
 * The using Volt component must declare a `public ?int $deleteAttachmentId = null`
 * property and implement `getFileableModel()` to return the parent model.
 *
 * @phpstan-ignore trait.unused (used by Volt components in Blade files)
 */
trait HasFileActions
{
    /**
     * Return the parent model that owns the attachments.
     */
    abstract protected function getFileableModel(): Model;

    #[On('file-uploaded')]
    public function refreshFiles(): void
    {
        $this->getFileableModel()->loadCount('attachments');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteAttachmentId = $id;
    }

    public function deleteAttachment(): void
    {
        if ($this->deleteAttachmentId) {
            $attachment = Attachment::findOrFail($this->deleteAttachmentId);
            Gate::authorize('delete', $attachment);
            app(FileService::class)->delete($attachment);
            $this->deleteAttachmentId = null;
            $this->getFileableModel()->loadCount('attachments');
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteAttachmentId = null;
    }

    /**
     * Build the shared data array for the file browser partial.
     *
     * @return array{grouped: Collection<string, Collection<int, Attachment>>, totalCount: int, fileService: FileService}
     */
    protected function fileData(): array
    {
        $attachments = $this->getFileableModel()->attachments()
            ->orderByDesc('created_at')
            ->get();

        $grouped = $attachments->groupBy(fn (Attachment $a) => $a->category ?? 'Uncategorised');
        $fileService = app(FileService::class);

        return [
            'grouped' => $grouped,
            'totalCount' => $attachments->count(),
            'fileService' => $fileService,
        ];
    }
}
