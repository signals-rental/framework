<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateSerialisedComponent;
use App\Actions\Products\DeleteSerialisedComponent;
use App\Actions\Products\UpdateSerialisedComponent;
use App\Data\Products\CreateSerialisedComponentData;
use App\Data\Products\SerialisedComponentData;
use App\Data\Products\UpdateSerialisedComponentData;
use App\Http\Controllers\Api\Controller;
use App\Models\Product;
use App\Models\SerialisedComponent;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manage a product's kit bill-of-materials (serialised_components) as a
 * sub-resource of products. Kits are plain Eloquent (not event-sourced); each
 * create/update/delete flows through its action, which enforces the depth/cycle
 * guard and maintains the `products.is_kit` flag.
 */
class SerialisedComponentController extends Controller
{
    /**
     * List a product's kit components.
     */
    #[ApiResponse(200, 'Component list', type: 'array{components: list<array{id: int, product_id: int, component_product_id: int, component_name: string, quantity: string, binding: string, sort_order: int}>}')]
    public function index(Product $product): JsonResponse
    {
        $this->authorizeApi('kits.view', 'kits:read');

        $components = $product->components()
            ->with('componentProduct')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = $components->map(
            fn (SerialisedComponent $component): array => SerialisedComponentData::fromModel($component)->toArray()
        )->all();

        return $this->respondWithCollection($items, 'components');
    }

    /**
     * Add a component to a product's kit composition.
     *
     * Rejected with `422` when the component is the product itself, would form a
     * cycle, would exceed the configured nesting depth, or is already a component
     * of the kit. The product's `is_kit` flag flips true on the first component.
     */
    #[ApiResponse(201, 'Component added')]
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('kits.manage', 'kits:write');

        $validated = $request->validate(CreateSerialisedComponentData::rules());
        $dto = CreateSerialisedComponentData::from(array_merge($validated, ['product_id' => $product->id]));

        $result = (new CreateSerialisedComponent)($dto);

        return $this->respondWith($result->toArray(), 'component', Response::HTTP_CREATED);
    }

    /**
     * Update a kit component line (quantity, binding, sort order).
     */
    #[ApiResponse(200, 'Component updated')]
    public function update(Request $request, Product $product, SerialisedComponent $component): JsonResponse
    {
        $this->authorizeApi('kits.manage', 'kits:write');

        $this->assertComponentBelongsToProduct($component, $product);

        $dto = UpdateSerialisedComponentData::from($request->validate(UpdateSerialisedComponentData::rules()));

        $result = (new UpdateSerialisedComponent)($component, $dto);

        return $this->respondWith($result->toArray(), 'component');
    }

    /**
     * Remove a component from a product's kit composition. The product's `is_kit`
     * flag flips false when the last component is removed.
     */
    #[ApiResponse(204, 'Component removed')]
    public function destroy(Product $product, SerialisedComponent $component): JsonResponse
    {
        $this->authorizeApi('kits.manage', 'kits:write');

        $this->assertComponentBelongsToProduct($component, $product);

        (new DeleteSerialisedComponent)($component);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Guard that a component line belongs to the bound product (else 404).
     */
    private function assertComponentBelongsToProduct(SerialisedComponent $component, Product $product): void
    {
        abort_unless($component->product_id === $product->id, Response::HTTP_NOT_FOUND);
    }
}
