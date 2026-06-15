<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListValues\CreateListValue;
use App\Actions\ListValues\DeleteListValue;
use App\Actions\ListValues\UpdateListValue;
use App\Data\ListValues\CreateListValueData;
use App\Data\ListValues\ListValueData;
use App\Data\ListValues\UpdateListValueData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ListName;
use App\Models\ListValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ListValueController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'parent_id',
        'is_active',
        'is_system',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * List values for a list name.
     *
     * Supports Ransack `q[...]` filtering — e.g. `?q[is_active_eq]=true` to
     * return only active values — and `sort` on the whitelisted fields.
     */
    public function index(Request $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.view', 'static-data:read');

        $query = $listName->values()->getQuery();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->filled('sort')) {
            $query->orderBy('sort_order');
        }

        $values = $query->get()
            ->map(fn (ListValue $value): array => ListValueData::fromModel($value)->toArray())
            ->all();

        return $this->respondWithCollection($values, 'list_values');
    }

    /**
     * Create a value for a list name.
     */
    public function store(Request $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $request->merge(['list_name_id' => $listName->id]);
        $validated = $request->validate(CreateListValueData::rules());
        $dto = CreateListValueData::from($validated);

        $result = (new CreateListValue)($dto);

        return $this->respondWith(
            $result->toArray(),
            'list_value',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a list value.
     */
    public function update(Request $request, ListName $listName, ListValue $listValue): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $validated = $request->validate(UpdateListValueData::rules());
        $dto = UpdateListValueData::from($validated);

        $result = (new UpdateListValue)($listValue, $dto);

        return $this->respondWith(
            $result->toArray(),
            'list_value',
        );
    }

    /**
     * Delete a list value.
     */
    public function destroy(ListName $listName, ListValue $listValue): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        (new DeleteListValue)($listValue);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
