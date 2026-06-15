<?php

namespace App\Actions\Products;

use App\Data\Products\ProductGroupData;
use App\Data\Products\UpdateProductGroupData;
use App\Events\AuditableEvent;
use App\Models\ProductGroup;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateProductGroup
{
    /**
     * Update an existing product group.
     *
     * Field update convention (Spatie Data Optional behaviour):
     *   - Absent key / `null` in DTO → field is not updated (retains current value)
     *   - Empty string `""` → field is cleared (set to null in database)
     *
     * `parent_id` is an explicit exception: it uses Spatie `Optional`, so an
     * absent value is omitted from `toArray()` (left untouched), while an
     * explicit `null` is preserved and clears the existing parent.
     */
    public function __invoke(ProductGroup $group, UpdateProductGroupData $data): ProductGroupData
    {
        Gate::authorize('products.edit');

        return DB::transaction(function () use ($group, $data): ProductGroupData {
            $attributes = $data->toArray();
            $parentIdProvided = array_key_exists('parent_id', $attributes);

            $updates = collect($attributes)
                ->except(['custom_fields'])
                ->reject(fn ($value) => $value === null)
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all();

            if ($parentIdProvided) {
                $updates['parent_id'] = $attributes['parent_id'];
            }

            $group->update($updates);

            if ($data->custom_fields !== null) {
                app(CustomFieldValidator::class)->validate('ProductGroup', $data->custom_fields);
                $group->syncCustomFields($data->custom_fields);
            }

            $group->refresh();

            event(new AuditableEvent($group, 'product_group.updated'));

            app(WebhookService::class)->dispatch('product_group.updated', [
                'product_group' => ProductGroupData::fromModel($group)->toArray(),
            ]);

            return ProductGroupData::fromModel($group);
        });
    }
}
