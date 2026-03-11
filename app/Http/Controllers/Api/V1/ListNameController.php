<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListValues\CreateListName;
use App\Actions\ListValues\DeleteListName;
use App\Actions\ListValues\UpdateListName;
use App\Data\ListValues\CreateListNameData;
use App\Data\ListValues\ListNameData;
use App\Data\ListValues\UpdateListNameData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ListName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ListNameController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_system',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    /**
     * List all list names.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('list-values.view', 'static-data:read');

        $query = ListName::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $lists = $paginator->getCollection()->map(
            fn (ListName $listName): array => ListNameData::fromModel($listName)->toArray()
        )->all();

        return $this->respondWithCollection($lists, 'list_names', $paginator);
    }

    /**
     * Show a single list name with its values.
     */
    public function show(ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.view', 'static-data:read');

        $listName->load('values');

        return $this->respondWith(
            ListNameData::fromModel($listName)->toArray(),
            'list_name',
        );
    }

    /**
     * Create a list name.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $validated = $request->validate(CreateListNameData::rules());
        $dto = CreateListNameData::from($validated);

        $result = (new CreateListName)($dto);

        return $this->respondWith(
            $result->toArray(),
            'list_name',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a list name.
     */
    public function update(Request $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $validated = $request->validate(UpdateListNameData::rules());
        $dto = UpdateListNameData::from($validated);

        $result = (new UpdateListName)($listName, $dto);

        return $this->respondWith(
            $result->toArray(),
            'list_name',
        );
    }

    /**
     * Delete a list name.
     */
    public function destroy(ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        (new DeleteListName)($listName);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
