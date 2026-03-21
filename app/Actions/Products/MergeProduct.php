<?php

namespace App\Actions\Products;

use App\Data\Products\MergeProductData;
use App\Events\AuditableEvent;
use App\Models\Accessory;
use App\Models\Product;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MergeProduct
{
    public function __invoke(MergeProductData $data): Product
    {
        Gate::authorize('products.delete');

        $primary = Product::findOrFail($data->primary_id);
        $secondary = Product::findOrFail($data->secondary_id);

        if ($primary->product_type !== $secondary->product_type) {
            throw new \InvalidArgumentException('Cannot merge products of different types.');
        }

        return DB::transaction(function () use ($primary, $secondary): Product {
            // Transfer stock levels
            $secondary->stockLevels()->update([
                'product_id' => $primary->id,
            ]);

            // Transfer accessories (both directions, skip duplicates)
            $existingAccessoryProductIds = $primary->accessories()
                ->pluck('accessory_product_id')
                ->toArray();

            $secondary->accessories()
                ->whereNotIn('accessory_product_id', [...$existingAccessoryProductIds, $primary->id])
                ->update(['product_id' => $primary->id]);

            $secondary->accessories()->delete();

            // Transfer inverse accessories (where secondary is used as an accessory)
            $existingInverseProductIds = Accessory::query()
                ->where('accessory_product_id', $primary->id)
                ->pluck('product_id')
                ->toArray();

            Accessory::query()
                ->where('accessory_product_id', $secondary->id)
                ->whereNotIn('product_id', [...$existingInverseProductIds, $primary->id])
                ->update(['accessory_product_id' => $primary->id]);

            Accessory::query()
                ->where('accessory_product_id', $secondary->id)
                ->delete();

            // Transfer attachments
            $secondary->attachments()->update([
                'attachable_id' => $primary->id,
            ]);

            // Copy missing custom field values
            $existingFieldIds = $primary->customFieldValues()->pluck('custom_field_id')->toArray();
            $secondary->customFieldValues()
                ->whereNotIn('custom_field_id', $existingFieldIds)
                ->update(['entity_id' => $primary->id]);

            $secondary->customFieldValues()->delete();

            // Audit and soft-delete secondary
            event(new AuditableEvent($primary, 'product.merged', null, null, [
                'secondary_id' => $secondary->id,
                'secondary_name' => $secondary->name,
            ]));

            app(WebhookService::class)->dispatch('product.merged', [
                'primary_id' => $primary->id,
                'secondary_id' => $secondary->id,
            ]);

            $secondary->delete();

            return $primary->fresh();
        });
    }
}
