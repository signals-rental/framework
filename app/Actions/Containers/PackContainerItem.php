<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerItemData;
use App\Data\Containers\PackContainerItemData;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Availability\ContainerDemandResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pack a single serialised item into an open container — the minimal write
 * surface M5-3b needs to create container demands and make availability correct.
 *
 * Containers are plain Eloquent (NOT event-sourced): this is a straight Eloquent
 * insert plus a demand sync, wrapped in one transaction so the membership row and
 * its container demand are written atomically. The {@see ContainerDemandResolver}
 * then decides whether to actually hold the item from availability (kit / hybrid-
 * fixed) or not (transport / hybrid-pool).
 *
 * Guards (hard blocks — these are data-integrity invariants, not the warn-and-
 * resolve workflow which is Phase 4):
 *  - the container must be OPEN;
 *  - the item must be a SERIALISED stock level at the container's store;
 *  - the item must not already be in an ACTIVE container (one membership per item)
 *    — this also backstops the Postgres partial unique index for the SQLite lane;
 *  - nesting a container item respects the outermost container's
 *    `container_max_nesting_depth`.
 *
 * The container's full conflict-resolution surface (transfer, auto-return,
 * checked-out override, sealed-container handling) is Phase 4.
 */
class PackContainerItem
{
    public function __invoke(Container $container, PackContainerItemData $data): ContainerItemData
    {
        Gate::authorize('containers.pack');

        $stockLevel = StockLevel::query()->findOrFail($data->serialised_item_id);

        $this->guard($container, $stockLevel);

        $item = DB::transaction(function () use ($container, $stockLevel, $data): ContainerItem {
            $item = new ContainerItem([
                'container_id' => $container->id,
                'serialised_item_id' => $stockLevel->id,
                'product_id' => $stockLevel->product_id,
                'packed_at' => Carbon::now('UTC'),
                'packed_by_user_id' => Auth::id(),
                'position' => $data->position,
                'notes' => $data->notes,
            ]);
            $item->save();

            // Mirror the CRMS-compat containment layer on the stock level so both
            // the operational overlay and the legacy column agree.
            if ($container->serialised_item_id !== null) {
                $stockLevel->forceFill([
                    'container_stock_level_id' => $container->serialised_item_id,
                    'container_mode' => $container->availabilityMode()->value,
                ])->save();
            }

            // Create the container demand (or no-op for transport / hybrid-pool).
            $item->setRelation('container', $container);
            app(ContainerDemandResolver::class)->syncDemands($item);

            return $item;
        });

        return ContainerItemData::fromModel($item->fresh() ?? $item);
    }

    /**
     * Enforce the hard packing invariants. Throws a validation exception with a
     * field-scoped message so API/Livewire callers surface it inline.
     *
     * @throws ValidationException
     */
    protected function guard(Container $container, StockLevel $stockLevel): void
    {
        if (! $container->status->acceptsPacking()) {
            throw ValidationException::withMessages([
                'container' => __('Items can only be packed into an open container.'),
            ]);
        }

        if (! $stockLevel->isSerialised()) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Only serialised items can be packed into a container.'),
            ]);
        }

        if (
            $container->store_id !== null
            && (int) $stockLevel->store_id !== (int) $container->store_id
        ) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is held at a different store to the container.'),
            ]);
        }

        // One active membership per item (also enforced by the Postgres partial
        // unique index; checked here for the SQLite lane and a friendlier error).
        $alreadyPacked = ContainerItem::query()
            ->where('serialised_item_id', $stockLevel->id)
            ->whereNull('unpacked_at')
            ->exists();

        if ($alreadyPacked) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is already packed in an active container.'),
            ]);
        }

        $this->guardNestingDepth($container, $stockLevel);
    }

    /**
     * When the packed item is itself a container housing, ensure nesting it does
     * not exceed the outermost container's configured max depth.
     *
     * Depth is the recursion from the outermost (root) container down to the
     * newly-nested child. A child container packed directly into a root is depth
     * 2 (root → child); the limit defaults to 2.
     *
     * @throws ValidationException
     */
    protected function guardNestingDepth(Container $container, StockLevel $stockLevel): void
    {
        $nested = Container::query()
            ->active()
            ->where('serialised_item_id', $stockLevel->id)
            ->first();

        if ($nested === null) {
            return;
        }

        // Resolve the outermost container's configured ceiling, walking up the
        // parent chain from the target container (bounded by a sane cycle guard).
        $root = $container;
        $guard = 0;

        while ($root->parent_container_id !== null && $guard < 10) {
            $parent = Container::query()->find($root->parent_container_id);

            if ($parent === null) {
                break;
            }

            $root = $parent;
            $guard++;
        }

        // Resolve the root's backing product's depth ceiling — the FK is nullable
        // (temporary containers have no product), so fall back to the default when
        // absent. Read the single column so the absence is a plain null.
        $maxDepth = max(1, (int) (Product::query()
            ->whereKey($root->product_id)
            ->value('container_max_nesting_depth') ?? 2));

        // Depth of the target container within the hierarchy (root = 1).
        $currentDepth = $this->depthOf($container);

        // Nesting the child adds one more level beneath the target container.
        if ($currentDepth + 1 > $maxDepth) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Nesting this container would exceed the maximum nesting depth.'),
            ]);
        }
    }

    /**
     * The 1-based depth of a container within its nesting hierarchy (a root
     * container is depth 1). Bounded by a cycle guard.
     */
    protected function depthOf(Container $container): int
    {
        $depth = 1;
        $current = $container;
        $guard = 0;

        while ($current->parent_container_id !== null && $guard < 10) {
            $parent = Container::query()->find($current->parent_container_id);

            if ($parent === null) {
                break;
            }

            $depth++;
            $current = $parent;
            $guard++;
        }

        return $depth;
    }
}
