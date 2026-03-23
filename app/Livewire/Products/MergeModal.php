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

    public string $mergeSearch = '';

    /** @var list<array{id: int, name: string}> */
    public array $mergeSearchResults = [];

    #[On('open-merge-modal')]
    public function openModal(int $productA, int $productB = 0): void
    {
        $this->productAId = $productA;
        $this->productBId = $productB > 0 ? $productB : null;
        $this->primaryId = $productA;
        $this->mergeSearch = '';
        $this->mergeSearchResults = [];
        $this->js("setTimeout(() => \$dispatch('open-modal', 'merge-products'), 50)");
    }

    public function updatedMergeSearch(string $value): void
    {
        if ($this->productAId === null) {
            $this->mergeSearchResults = [];

            return;
        }

        if (mb_strlen($value) < 2) {
            $this->mergeSearchResults = [];

            return;
        }

        $this->mergeSearchResults = Product::query()
            ->where('name', 'ilike', '%'.addcslashes($value, '%_').'%')
            ->where('id', '!=', $this->productAId)
            ->where('is_active', true)
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn (Product $p): array => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    public function selectMergeTarget(int $id): void
    {
        $this->productBId = $id;
        $this->mergeSearch = '';
        $this->mergeSearchResults = [];
    }

    public function clearMergeTarget(): void
    {
        $this->productBId = null;
        $this->mergeSearch = '';
        $this->mergeSearchResults = [];
    }

    public function merge(): void
    {
        if (! $this->primaryId || ! $this->productAId || ! $this->productBId) {
            session()->flash('error', 'Please select both products before merging.');

            return;
        }

        if (! in_array($this->primaryId, [$this->productAId, $this->productBId], true)) {
            session()->flash('error', 'Primary product must be one of the selected products.');

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
