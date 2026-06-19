<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\UnpackContainerItem;
use App\Data\Containers\ContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Container;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API surface for the container availability subset (serialised-containers.md,
 * M5-3b). Only the reads (list/show) and the pack/unpack availability write
 * surface are exposed here — the broader container lifecycle (seal, dissolve,
 * scan, repack, dispatch, return) stays Phase-4 and is intentionally absent.
 *
 * Pack/unpack delegate to the existing {@see PackContainerItem} /
 * {@see UnpackContainerItem} actions, which create/release the container demand
 * inside a single transaction. The already-booked-asset guard surfaces as a 422.
 */
class ContainerController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'barcode',
        'status',
        'scan_mode',
        'is_temporary',
        'product_id',
        'store_id',
        'opportunity_id',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'barcode',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'product',
        'store',
        'activeItems',
        'containerItems',
    ];

    /**
     * List containers with filtering, sorting, and pagination.
     */
    #[ApiResponse(200, 'Container list', type: 'array{containers: list<array{id: int, uuid: string, name: string, barcode: string|null, status: string, scan_mode: string, is_temporary: bool, product_id: int|null, store_id: int|null, opportunity_id: int|null, availability_mode: string, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('containers.view', 'containers:read');

        $query = Container::query();
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $containers = $paginator->getCollection()->map(
            fn (Container $container): array => ContainerData::fromModel($container)->toArray()
        )->all();

        return response()->json([
            'containers' => $containers,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * Show a single container with its currently-packed items (`?include=items`).
     */
    #[ApiResponse(200, 'Container details', type: 'array{container: array{id: int, uuid: string, name: string, status: string, availability_mode: string}}')]
    public function show(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.view', 'containers:read');

        $this->applyIncludes(Container::query(), $request, $container);

        // show always returns the currently-packed contents.
        $container->loadMissing('activeItems');

        return $this->respondWith(
            ContainerData::fromModel($container)->toArray(),
            'container',
        );
    }

    /**
     * Pack a serialised item into an open container.
     *
     * Creates the membership row and (for kit / hybrid-fixed containers) the
     * container demand that holds the item from individual availability. Packing
     * a non-serialised item, an item at a different store, an item already in an
     * active container, or an item already committed to an opportunity yields a
     * 422.
     */
    #[ApiResponse(201, 'Item packed')]
    public function pack(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.pack', 'containers:write');

        $data = PackContainerItemData::from($request->validate(PackContainerItemData::rules()));

        $item = (new PackContainerItem)($container, $data);

        return $this->respondWith($item->toArray(), 'container_item', Response::HTTP_CREATED);
    }

    /**
     * Unpack a serialised item from an open container.
     *
     * Soft-closes the active membership and releases its container demand so the
     * item returns to individual availability. Unpacking an item that is not
     * packed in this container yields a 422.
     */
    #[ApiResponse(200, 'Item unpacked')]
    public function unpack(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.pack', 'containers:write');

        $data = UnpackContainerItemData::from($request->validate(UnpackContainerItemData::rules()));

        $item = (new UnpackContainerItem)($container, $data);

        return $this->respondWith($item->toArray(), 'container_item');
    }
}
