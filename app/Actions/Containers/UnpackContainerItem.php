<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\StockLevel;
use App\Services\Availability\ContainerDemandResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Unpack a serialised item from an open container — soft-closing the active
 * membership row and releasing its container demand so the item returns to
 * individual availability (serialised-containers.md §"Kit Mode" — unpack removes
 * the demand).
 *
 * Plain Eloquent + resolver call, wrapped in one transaction so the membership
 * close and the demand release are atomic.
 *
 * Guards: the container must be OPEN, and an ACTIVE membership for the item must
 * exist in this container.
 */
class UnpackContainerItem
{
    public function __invoke(Container $container, UnpackContainerItemData $data): ContainerItemData
    {
        Gate::authorize('containers.pack');

        if (! $container->status->acceptsPacking()) {
            throw ValidationException::withMessages([
                'container' => __('Items can only be unpacked from an open container.'),
            ]);
        }

        $item = ContainerItem::query()
            ->where('container_id', $container->id)
            ->where('serialised_item_id', $data->serialised_item_id)
            ->whereNull('unpacked_at')
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is not packed in this container.'),
            ]);
        }

        $item = DB::transaction(function () use ($container, $item, $data): ContainerItem {
            $item->forceFill([
                'unpacked_at' => Carbon::now('UTC'),
                'unpacked_reason' => $data->reason,
            ])->save();

            // Clear the CRMS-compat containment mirror on the stock level.
            StockLevel::query()
                ->whereKey($item->serialised_item_id)
                ->update([
                    'container_stock_level_id' => null,
                    'container_mode' => null,
                ]);

            // Release the container demand — the item returns to availability.
            $item->setRelation('container', $container);
            app(ContainerDemandResolver::class)->releaseDemands($item);

            return $item;
        });

        return ContainerItemData::fromModel($item->fresh() ?? $item);
    }
}
