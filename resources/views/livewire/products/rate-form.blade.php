<?php

use App\Actions\Rates\CreateProductRate;
use App\Actions\Rates\UpdateProductRate;
use App\Data\Rates\CreateProductRateData;
use App\Data\Rates\UpdateProductRateData;
use App\Enums\RateTransactionType;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Services\RateEngine\ProductRateOverlapChecker;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public ?int $rateId = null;

    public ?int $rateDefinitionId = null;

    public string $transactionType = 'rental';

    public string $price = '';

    public string $currency = 'GBP';

    public ?int $storeId = null;

    public ?string $validFrom = null;

    public ?string $validTo = null;

    public int $priority = 0;

    public function mount(Product $product, ?ProductRate $rate = null): void
    {
        Gate::authorize('rates.view');

        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments', 'rates']);
        $this->currency = (string) (settings('company.base_currency') ?? 'GBP');
        $this->validFrom = now()->toDateString();

        if ($rate?->exists) {
            abort_unless($rate->product_id === $product->id, 404);

            $this->rateId = $rate->id;
            $this->rateDefinitionId = $rate->rate_definition_id;
            $this->transactionType = $rate->transaction_type->value;
            $this->price = (string) Money::ofMinor($rate->price, $rate->currency)->getAmount();
            $this->currency = $rate->currency;
            $this->storeId = $rate->store_id;
            $this->validFrom = $rate->valid_from?->toDateString();
            $this->validTo = $rate->valid_to?->toDateString();
            $this->priority = $rate->priority;
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.($this->rateId ? ' — Edit Rate' : ' — Assign Rate'));
    }

    public function save(): void
    {
        $this->validate([
            'rateDefinitionId' => ['required', 'integer', 'exists:rate_definitions,id'],
            'transactionType' => ['required', new Enum(RateTransactionType::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'storeId' => ['nullable', 'integer', 'exists:stores,id'],
            'validFrom' => ['nullable', 'date'],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
            'priority' => ['integer'],
        ]);

        $minor = Money::of($this->price, $this->currency, roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();

        $payload = [
            'rate_definition_id' => $this->rateDefinitionId,
            'transaction_type' => $this->transactionType,
            'price' => $minor,
            'currency' => $this->currency,
            'store_id' => $this->storeId,
            'valid_from' => $this->validFrom ?: null,
            'valid_to' => $this->validTo ?: null,
            'priority' => $this->priority,
        ];

        if ($this->rateId !== null) {
            $rate = $this->product->rates()->findOrFail($this->rateId);
            $result = (new UpdateProductRate)($rate, UpdateProductRateData::from($payload));
        } else {
            $result = (new CreateProductRate)(CreateProductRateData::from(['product_id' => $this->product->id, ...$payload]));
        }

        $overlaps = app(ProductRateOverlapChecker::class)->overlapping(
            $this->product->id,
            $this->storeId,
            RateTransactionType::from($this->transactionType),
            $this->priority,
            $this->validFrom ?: null,
            $this->validTo ?: null,
            $result->id,
        );

        if ($overlaps->isNotEmpty()) {
            session()->flash('rate-overlap-warning', $overlaps->count());
        }

        $this->redirect(route('products.rates', $this->product), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->rateId !== null,
            'rateDefinitions' => RateDefinition::query()->orderByDesc('is_preset')->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'transactionTypes' => RateTransactionType::cases(),
            'currencies' => Currency::query()->enabled()->orderBy('code')->get(['code', 'name']),
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => $isEditing ? 'Edit Rate' : 'Assign Rate'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'rates'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Rate Assignment" description="Choose the rate definition and unit price the engine operates on. Priority breaks ties when rates overlap — higher wins.">
                <div class="space-y-4">
                    <flux:select wire:model="rateDefinitionId" label="Rate Definition" required>
                        <flux:select.option value="">Select a rate definition…</flux:select.option>
                        @foreach($rateDefinitions as $definition)
                            <flux:select.option value="{{ $definition->id }}">{{ $definition->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model="transactionType" label="Transaction Type">
                            @foreach($transactionTypes as $type)
                                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="priority" type="number" label="Priority" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="price" type="text" inputmode="decimal" label="Unit Price" placeholder="0.00" required />
                        <flux:select wire:model="currency" label="Currency">
                            @foreach($currencies as $c)
                                <flux:select.option value="{{ $c->code }}">{{ $c->code }} — {{ $c->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Scope" description="Optionally limit this rate to a store and a validity window.">
                <div class="space-y-4">
                    <flux:select wire:model="storeId" label="Store">
                        <flux:select.option value="">All stores</flux:select.option>
                        @foreach($stores as $store)
                            <flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="validFrom" type="date" label="Valid From" />
                        <flux:input wire:model="validTo" type="date" label="Valid To" />
                    </div>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Rate' : 'Assign Rate' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('products.rates', $product) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
