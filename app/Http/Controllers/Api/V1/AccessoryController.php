<?php

namespace App\Http\Controllers\Api\V1;

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

        $items = $accessories->map(fn (Accessory $acc): array => [
            'id' => $acc->id,
            'product_id' => $acc->product_id,
            'accessory_product_id' => $acc->accessory_product_id,
            'related_name' => $acc->accessoryProduct->name ?? '',
            'quantity' => number_format((float) $acc->quantity, 1, '.', ''),
            'included' => $acc->included,
            'zero_priced' => $acc->zero_priced,
            'sort_order' => $acc->sort_order,
        ])->all();

        return $this->respondWithCollection($items, 'accessories');
    }

    /**
     * Create an accessory for a product.
     */
    #[ApiResponse(201, 'Accessory created', type: 'array{accessory: array{id: int, product_id: int, accessory_product_id: int, related_name: string, quantity: string, included: bool, zero_priced: bool, sort_order: int}}')]
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeApi('products.edit', 'products:write');

        $validated = $request->validate([
            'accessory_product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'included' => ['sometimes', 'boolean'],
            'zero_priced' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $accessory = $product->accessories()->create(array_merge(
            $validated,
            ['product_id' => $product->id],
        ));

        $accessory->load('accessoryProduct');

        return $this->respondWith([
            'id' => $accessory->id,
            'product_id' => $accessory->product_id,
            'accessory_product_id' => $accessory->accessory_product_id,
            'related_name' => $accessory->accessoryProduct->name ?? '',
            'quantity' => number_format((float) $accessory->quantity, 1, '.', ''),
            'included' => $accessory->included,
            'zero_priced' => $accessory->zero_priced,
            'sort_order' => $accessory->sort_order,
        ], 'accessory', Response::HTTP_CREATED);
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

        $accessory->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
