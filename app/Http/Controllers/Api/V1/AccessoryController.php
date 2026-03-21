<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateAccessory;
use App\Actions\Products\DeleteAccessory;
use App\Actions\Products\UpdateAccessory;
use App\Data\Products\AccessoryData;
use App\Data\Products\CreateAccessoryData;
use App\Data\Products\UpdateAccessoryData;
use App\Http\Controllers\Api\Controller;
use App\Models\Accessory;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessoryController extends Controller
{
    /**
     * List accessories for a product.
     */
    #[ApiResponse(200, 'Accessory list', type: 'array{accessories: list<array{id: int, product_id: int, accessory_product_id: int, related_name: string, quantity: string, included: bool, zero_priced: bool, sort_order: int}>}')]
    public function index(Product $product): JsonResponse
    {
        $this->authorizeApi('products.view', 'products:read');

        $accessories = $product->accessories()->with('accessoryProduct')->get();

        $items = $accessories->map(
            fn (Accessory $acc): array => AccessoryData::fromModel($acc)->toArray()
        )->all();

        return $this->respondWithCollection($items, 'accessories');
    }

    /**
     * Create an accessory for a product.
     */
    #[ApiResponse(201, 'Accessory created', type: 'array{accessory: array{id: int, product_id: int, accessory_product_id: int, related_name: string, quantity: string, included: bool, zero_priced: bool, sort_order: int}}')]
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        $validated = $request->validate(CreateAccessoryData::rules());
        $dto = CreateAccessoryData::from(array_merge($validated, ['product_id' => $product->id]));

        $result = (new CreateAccessory)($dto);

        return $this->respondWith($result->toArray(), 'accessory', Response::HTTP_CREATED);
    }

    /**
     * Update an accessory for a product.
     */
    #[ApiResponse(200, 'Accessory updated', type: 'array{accessory: array{id: int, product_id: int, accessory_product_id: int, related_name: string, quantity: string, included: bool, zero_priced: bool, sort_order: int}}')]
    public function update(Request $request, Product $product, Accessory $accessory): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        if ($accessory->product_id !== $product->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate(UpdateAccessoryData::rules());
        $dto = UpdateAccessoryData::from($validated);

        $result = (new UpdateAccessory)($accessory, $dto);

        return $this->respondWith($result->toArray(), 'accessory');
    }

    /**
     * Delete an accessory from a product.
     */
    #[ApiResponse(204, 'Accessory removed')]
    public function destroy(Product $product, Accessory $accessory): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        if ($accessory->product_id !== $product->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        (new DeleteAccessory)($accessory);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
