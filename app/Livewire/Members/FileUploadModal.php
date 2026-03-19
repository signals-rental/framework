<?php

namespace App\Livewire\Members;

use App\Models\ListName;
use App\Models\Member;
use App\Services\FileService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileUploadModal extends Component
{
    use WithFileUploads;

    public int $memberId;

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
        $this->validate([
            'attachment' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $member = Member::findOrFail($this->memberId);
        app(FileService::class)->upload($this->attachment, $member, $this->category, $this->description ?: null);

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

        return view('livewire.members.file-upload-modal', [
            'categories' => $categories,
        ]);
    }
}
