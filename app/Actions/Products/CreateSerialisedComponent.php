<?php

namespace App\Actions\Products;

use App\Data\Products\CreateSerialisedComponentData;
use App\Data\Products\SerialisedComponentData;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Services\Api\WebhookService;
use App\Services\Availability\KitCompositionGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Add a component line to a product's kit bill-of-materials.
 *
 * Plain Eloquent (kits are NOT event-sourced). Enforces the create-time depth /
 * cycle guard so the composition can never exceed the configured nesting depth
 * or form a cycle, and maintains the denormalised `products.is_kit` flag — set
 * true as soon as the product owns a component. The membership insert and the
 * flag update run in one transaction so the two never disagree.
 */
class CreateSerialisedComponent
{
    public function __invoke(CreateSerialisedComponentData $data): SerialisedComponentData
    {
        Gate::authorize('kits.manage');

        $product = Product::query()->findOrFail($data->product_id);

        $component = DB::transaction(function () use ($product, $data): SerialisedComponent {
            // Run the duplicate and depth/cycle checks INSIDE the transaction so a
            // concurrent create cannot slip between the check and the insert.
            $exists = SerialisedComponent::query()
                ->where('product_id', $product->id)
                ->where('component_product_id', $data->component_product_id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'component_product_id' => __('This product is already a component of the kit.'),
                ]);
            }

            // Depth / cycle integrity — rejected as a 422 before anything is written.
            app(KitCompositionGuard::class)->assertCanAdd($product->id, $data->component_product_id);

            try {
                $component = $product->components()->create([
                    'component_product_id' => $data->component_product_id,
                    'quantity' => $data->quantity,
                    'binding' => $data->binding,
                    'sort_order' => $data->sort_order,
                ]);
            } catch (QueryException) {
                // A concurrent create won the race on the Postgres unique constraint
                // (uq_kit_component). Surface it as a friendly 422 rather than a raw 500.
                throw ValidationException::withMessages([
                    'component_product_id' => __('This product is already a component of the kit.'),
                ]);
            }

            // First component makes the product a kit.
            if (! $product->is_kit) {
                $product->forceFill(['is_kit' => true])->save();
            }

            return $component;
        });

        $component->load('componentProduct');

        event(new AuditableEvent($component, 'serialised_component.created'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $product->id,
        ]);

        return SerialisedComponentData::fromModel($component);
    }
}
