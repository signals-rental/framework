<?php

namespace App\Livewire\Products;

use App\Actions\Products\MergeProduct;
use App\Data\Products\MergeProductData;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class MergeModal extends Component
{
    public ?int $productAId = null;

    public ?int $productBId = null;

    public ?int $primaryId = null;

    #[On('open-merge-modal')]
    public function openModal(int $productA, int $productB): void
    {
        $this->productAId = $productA;
        $this->productBId = $productB;
        $this->primaryId = $productA;
        $this->js("setTimeout(() => \$dispatch('open-modal', 'merge-products'), 50)");
    }

    public function merge(): void
    {
        if (! $this->primaryId || ! $this->productAId || ! $this->productBId) {
            session()->flash('error', 'Please select both products before merging.');

            return;
        }

        $secondaryId = $this->primaryId === $this->productAId
            ? $this->productBId
            : $this->productAId;

        try {
            (new MergeProduct)(MergeProductData::from([
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
            ]));
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            session()->flash('error', 'One of the selected products no longer exists.');

            return;
        } catch (\Throwable $e) {
            Log::error('Product merge failed', [
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'An unexpected error occurred while merging. Please try again.');

            return;
        }

        $this->dispatch('product-merged');
        $this->redirect(route('products.show', $this->primaryId), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $productA = $this->productAId ? Product::withCount(['stockLevels', 'accessories', 'attachments'])->find($this->productAId) : null;
        $productB = $this->productBId ? Product::withCount(['stockLevels', 'accessories', 'attachments'])->find($this->productBId) : null;

        return [
            'productA' => $productA,
            'productB' => $productB,
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.products.merge-modal');
    }
}
