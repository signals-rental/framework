<?php

namespace App\Services\Availability;

use App\Enums\KitComponentBinding;
use App\Models\SerialisedComponent;
use Illuminate\Validation\ValidationException;

/**
 * Create-time integrity guard for kit compositions (serialised_components).
 *
 * The {@see KitAvailabilityCalculator} enforces the nesting depth and cycle
 * bounds at read time as a backstop, but a composition that breaks them should
 * never be persisted in the first place. This guard runs the same checks up
 * front so a bad component is rejected with a friendly 422 rather than blowing
 * up later read paths.
 *
 * Two invariants are enforced when adding `component` under `parent`:
 *
 *  - **No cycle** — the component must not be the parent itself, nor any
 *    ancestor of the parent (which would make the kit contain itself,
 *    directly or transitively).
 *  - **Depth bound** — the deepest path through the resulting composition
 *    (the parent's own ancestor depth + the component's own subtree depth)
 *    must not exceed `availability.kit_nesting_max_depth`.
 */
class KitCompositionGuard
{
    /**
     * Validate that adding `$componentProductId` as a component of
     * `$parentProductId` keeps the composition acyclic and within the configured
     * nesting depth.
     *
     * @throws ValidationException
     */
    public function assertCanAdd(int $parentProductId, int $componentProductId): void
    {
        if ($componentProductId === $parentProductId) {
            throw ValidationException::withMessages([
                'component_product_id' => __('A product cannot be a component of itself.'),
            ]);
        }

        // Cycle: the component must not already (transitively) contain the parent,
        // i.e. the parent must not appear in the component's own subtree.
        if ($this->reaches($componentProductId, $parentProductId)) {
            throw ValidationException::withMessages([
                'component_product_id' => __('Adding this component would create a circular kit composition.'),
            ]);
        }

        $max = max(1, (int) config('availability.kit_nesting_max_depth', 3));

        // Depth of the deepest path running THROUGH the new edge: how many levels
        // sit above the parent already, plus the parent→component edge, plus the
        // component's own subtree depth.
        $parentAncestorDepth = $this->ancestorDepth($parentProductId);
        $componentSubtreeDepth = $this->subtreeDepth($componentProductId);

        // Levels counted as edges: parentAncestorDepth (edges above parent) + 1
        // (parent→component) + componentSubtreeDepth (edges below component).
        $resultingDepth = $parentAncestorDepth + 1 + $componentSubtreeDepth;

        if ($resultingDepth > $max) {
            throw ValidationException::withMessages([
                'component_product_id' => __('Adding this component would exceed the maximum kit nesting depth of :max.', ['max' => $max]),
            ]);
        }
    }

    /**
     * Whether `$target` is reachable from `$start` by walking the component edges
     * (i.e. `$target` sits somewhere in `$start`'s composition subtree).
     */
    private function reaches(int $start, int $target, int $guard = 0): bool
    {
        if ($guard > 50) {
            return false;
        }

        $childIds = $this->childIds($start);

        if (in_array($target, $childIds, true)) {
            return true;
        }

        foreach ($childIds as $childId) {
            if ($this->reaches($childId, $target, $guard + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The number of edges on the longest chain of parents ABOVE `$productId`
     * (i.e. how deeply this product is already nested as a component elsewhere).
     * A product used in no kit returns 0.
     */
    private function ancestorDepth(int $productId, int $guard = 0): int
    {
        if ($guard > 50) {
            return $guard;
        }

        /** @var list<int> $parentIds */
        $parentIds = SerialisedComponent::query()
            ->where('component_product_id', $productId)
            ->pluck('product_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $deepest = 0;

        foreach ($parentIds as $parentId) {
            $deepest = max($deepest, 1 + $this->ancestorDepth($parentId, $guard + 1));
        }

        return $deepest;
    }

    /**
     * The number of edges on the longest chain of components BELOW `$productId`
     * (i.e. how deep this product's own kit subtree runs). A leaf product
     * returns 0.
     */
    private function subtreeDepth(int $productId, int $guard = 0): int
    {
        if ($guard > 50) {
            return $guard;
        }

        $childIds = $this->childIds($productId);

        $deepest = 0;

        foreach ($childIds as $childId) {
            $deepest = max($deepest, 1 + $this->subtreeDepth($childId, $guard + 1));
        }

        return $deepest;
    }

    /**
     * The distinct component product ids directly composing `$productId`.
     *
     * @return list<int>
     */
    private function childIds(int $productId): array
    {
        return SerialisedComponent::query()
            ->where('product_id', $productId)
            ->pluck('component_product_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether the given binding is a recognised kit-component binding string.
     */
    public static function isValidBinding(string $binding): bool
    {
        return KitComponentBinding::tryFrom($binding) !== null;
    }
}
