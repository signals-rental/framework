<?php

namespace App\Livewire\Components;

use App\Models\Member;
use App\Services\FileService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class IconUpload extends Component
{
    use WithFileUploads;

    /** @var list<class-string<Model>> */
    private array $allowedModels = [
        Member::class,
        \App\Models\Product::class,
    ];

    public int $modelId;

    public string $modelClass;

    /** Raw S3/storage path for the icon. */
    public ?string $iconPath = null;

    /** Raw S3/storage path for the thumbnail. */
    public ?string $thumbPath = null;

    #[Validate('nullable|image|max:2048|mimes:jpeg,jpg,png,webp,gif')]
    public mixed $photo = null;

    public function mount(Model $model): void
    {
        $this->modelId = $model->getKey();
        $this->modelClass = $model::class;
        $this->iconPath = $model->getAttribute('icon_url');
        $this->thumbPath = $model->getAttribute('icon_thumb_url');
    }

    /**
     * Get a displayable URL for the thumbnail.
     */
    public function getThumbDisplayUrlProperty(): ?string
    {
        if ($this->thumbPath === null) {
            return null;
        }

        try {
            return app(\App\Services\FileService::class)->signedUrl($this->thumbPath);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function updatedPhoto(): void
    {
        $this->validate();

        $model = $this->resolveModel();
        Gate::authorize('update', $model);

        try {
            $fileService = app(FileService::class);
            $result = $fileService->uploadIcon($this->photo, $model);

            $model->update([
                'icon_url' => $result['icon_url'],
                'icon_thumb_url' => $result['icon_thumb_url'],
            ]);

            $this->iconPath = $result['icon_url'];
            $this->thumbPath = $result['icon_thumb_url'];
            $this->photo = null;

            $this->dispatch('icon-updated');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('photo', 'Failed to upload icon. Please try again.');
        }
    }

    public function removeIcon(): void
    {
        $model = $this->resolveModel();
        Gate::authorize('update', $model);

        $oldIconUrl = $model->getAttribute('icon_url');
        $oldThumbUrl = $model->getAttribute('icon_thumb_url');

        // Attempt storage delete first — if it fails, the DB still has valid paths
        $disk = config('filesystems.default', 'local');
        $storageDisk = $disk === 'local' ? 'public' : $disk;

        if ($oldIconUrl) {
            try {
                Storage::disk($storageDisk)->delete($oldIconUrl);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete icon file from storage', [
                    'path' => $oldIconUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        if ($oldThumbUrl) {
            try {
                Storage::disk($storageDisk)->delete($oldThumbUrl);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete icon thumbnail from storage', [
                    'path' => $oldThumbUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $model->update([
            'icon_url' => null,
            'icon_thumb_url' => null,
        ]);

        $this->iconPath = null;
        $this->thumbPath = null;

        $this->dispatch('icon-updated');
    }

    public function render(): View
    {
        return view('livewire.components.icon-upload');
    }

    /**
     * Resolve the model, validating the class is in the allowlist.
     */
    private function resolveModel(): Model
    {
        if (! in_array($this->modelClass, $this->allowedModels, true)) {
            abort(403, 'Invalid model class.');
        }

        return $this->modelClass::findOrFail($this->modelId);
    }
}
