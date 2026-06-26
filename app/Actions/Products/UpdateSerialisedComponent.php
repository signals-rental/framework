<?php

namespace App\Actions\Products;

use App\Data\Products\SerialisedComponentData;
use App\Data\Products\UpdateSerialisedComponentData;
use App\Events\AuditableEvent;
use App\Models\ContainerItem;
use App\Models\SerialisedComponent;
use App\Services\Api\WebhookService;
use App\Services\Availability\ContainerDemandResolver;
use Illuminate\Support\Facades\Gate;

/**
 * Update a kit composition line (quantity, binding, sort order). The kit parent
 * and component product are immutable here — only the per-line attributes
 * change — so no depth/cycle re-check is needed (the shape is unchanged).
 *
 * A `binding` change (Fixed ↔ Pool), however, flips whether members of this slot
 * are held from individual availability by a standing container demand
 * ({@see ContainerDemandResolver::shouldHoldFromAvailability()} reads the binding
 * live). Any items already packed into a hybrid-mode container of this composition
 * therefore carry now-stale demands; we re-sync them so availability converges.
 */
class UpdateSerialisedComponent
{
    public function __invoke(SerialisedComponent $component, UpdateSerialisedComponentData $data): SerialisedComponentData
    {
        Gate::authorize('kits.manage');

        $attributes = [];

        if ($data->quantity !== null) {
            $attributes['quantity'] = $data->quantity;
        }

        $bindingChanged = $data->binding !== null && $data->binding !== $component->binding;

        if ($data->binding !== null) {
            $attributes['binding'] = $data->binding;
        }

        if ($data->sort_order !== null) {
            $attributes['sort_order'] = $data->sort_order;
        }

        if ($attributes !== []) {
            $component->fill($attributes)->save();
        }

        if ($bindingChanged) {
            $this->resyncPackedMembers($component);
        }

        $component->load('componentProduct');

        event(new AuditableEvent($component, 'serialised_component.updated'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $component->product_id,
        ]);

        return SerialisedComponentData::fromModel($component);
    }

    /**
     * Re-sync container demands for every item currently packed into a container of
     * this composition whose product matches the changed component slot, so a
     * Fixed ↔ Pool binding flip immediately corrects each member's availability hold.
     */
    private function resyncPackedMembers(SerialisedComponent $component): void
    {
        $resolver = app(ContainerDemandResolver::class);

        ContainerItem::query()
            ->active()
            ->where('product_id', $component->component_product_id)
            ->whereHas('container', fn ($query) => $query->where('product_id', $component->product_id))
            ->get()
            ->each(fn (ContainerItem $item) => $resolver->syncDemands($item));
    }
}
