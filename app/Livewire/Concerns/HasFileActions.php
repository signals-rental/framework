<?php

namespace App\Livewire\Concerns;

use App\Models\Attachment;
use App\Services\FileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
        if (! $this->deleteAttachmentId) {
            return;
        }

        try {
            $attachment = Attachment::findOrFail($this->deleteAttachmentId);
            Gate::authorize('delete', $attachment);
            app(FileService::class)->delete($attachment);
        } catch (ModelNotFoundException) {
            session()->flash('info', 'File was already deleted.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to delete this file.');
        } catch (\Throwable $e) {
            Log::error('File deletion failed', [
                'attachment_id' => $this->deleteAttachmentId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'The file could not be deleted. Please try again.');
        }

        $this->deleteAttachmentId = null;
        $this->getFileableModel()->loadCount('attachments');
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
