<?php

use App\Livewire\Concerns\HasFileActions;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use HasFileActions;

    public Product $product;
    public ?int $deleteAttachmentId = null;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name . ' — Files');
    }

    protected function getFileableModel(): Product
    {
        return $this->product;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return $this->fileData();
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Files'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'files'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Toolbar --}}
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                Files ({{ $totalCount }})
            </h3>
            <button
                x-data
                x-on:click="$dispatch('open-file-upload')"
                class="s-btn s-btn-sm s-btn-primary"
            >
                Upload File
            </button>
        </div>

        @include('livewire.partials.file-browser', ['entityLabel' => 'product'])
    </div>

    {{-- Upload Modal (separate Livewire component for file upload isolation) --}}
    <livewire:components.file-upload-modal :model-type="\App\Models\Product::class" :model-id="$product->id" />
</section>
