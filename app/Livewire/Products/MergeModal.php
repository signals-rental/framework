<?php

namespace App\Livewire\Products;

use App\Actions\Products\MergeProduct;
use App\Data\Products\MergeProductData;
use App\Livewire\Concerns\HandlesMergeErrors;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class MergeModal extends Component
{
    use HandlesMergeErrors;

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
            ->whereLike('name', '%'.addcslashes($value, '%_').'%', caseSensitive: false)
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

        $succeeded = $this->runGuardedMerge(
            fn () => (new MergeProduct)(MergeProductData::validateAndCreate([
                'primary_id' => $this->primaryId,
                'secondary_id' => $secondaryId,
            ])),
            entityLabel: 'product',
            logContext: ['primary_id' => $this->primaryId, 'secondary_id' => $secondaryId],
        );

        if (! $succeeded) {
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
        $productA = $this->productAId ? Product::withCount(['stockLevels', 'accessories', 'attachments', 'customFieldValues'])->find($this->productAId) : null;
        $productB = $this->productBId ? Product::withCount(['stockLevels', 'accessories', 'attachments', 'customFieldValues'])->find($this->productBId) : null;

        return [
            'productA' => $productA,
            'productB' => $productB,
        ];
    }

    public function render(): View
    {
        return view('livewire.products.merge-modal');
    }
}
