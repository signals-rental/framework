<?php

namespace App\Actions\Products;

use App\Data\Products\SerialisedComponentData;
use App\Data\Products\UpdateSerialisedComponentData;
use App\Events\AuditableEvent;
use App\Models\SerialisedComponent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

/**
 * Update a kit composition line (quantity, binding, sort order). The kit parent
 * and component product are immutable here — only the per-line attributes
 * change — so no depth/cycle re-check is needed (the shape is unchanged).
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

        if ($data->binding !== null) {
            $attributes['binding'] = $data->binding;
        }

        if ($data->sort_order !== null) {
            $attributes['sort_order'] = $data->sort_order;
        }

        if ($attributes !== []) {
            $component->fill($attributes)->save();
        }

        $component->load('componentProduct');

        event(new AuditableEvent($component, 'serialised_component.updated'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $component->product_id,
        ]);

        return SerialisedComponentData::fromModel($component);
    }
}
