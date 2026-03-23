<?php

namespace App\Livewire\Components;

use App\Models\ListName;
use App\Services\FileService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileUploadModal extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $modelType;

    #[Locked]
    public int $modelId;

    public mixed $attachment = null;

    public ?string $category = null;

    public string $description = '';

    public bool $show = false;

    #[On('open-file-upload')]
    public function open(): void
    {
        $this->show = true;
    }

    public function close(): void
    {
        $this->reset(['attachment', 'category', 'description', 'show']);
    }

    public function save(): void
    {
        $permission = Str::plural(class_basename($this->modelType)).'.edit';
        $permission = Str::lower($permission);
        Gate::authorize($permission);

        $this->validate([
            'attachment' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $model = $this->modelType::findOrFail($this->modelId);
            app(FileService::class)->upload($this->attachment, $model, $this->category, $this->description ?: null);
        } catch (ModelNotFoundException $e) {
            Log::error('FileUploadModal: model not found', [
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);
            $this->addError('attachment', 'The record could not be found. It may have been deleted.');

            return;
        } catch (\RuntimeException $e) {
            Log::error('FileUploadModal: upload failed', [
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);
            $this->addError('attachment', 'The file could not be uploaded. Please try again.');

            return;
        }

        $this->reset(['attachment', 'category', 'description', 'show']);
        $this->dispatch('file-uploaded');
    }

    public function render(): View
    {
        $categories = ListName::query()
            ->where('name', 'File Category')
            ->first()
            ?->values()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all() ?? [];

        return view('livewire.components.file-upload-modal', [
            'categories' => $categories,
        ]);
    }
}
