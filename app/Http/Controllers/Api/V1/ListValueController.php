<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListValues\CreateListValue;
use App\Actions\ListValues\DeleteListValue;
use App\Actions\ListValues\UpdateListValue;
use App\Data\ListValues\CreateListValueData;
use App\Data\ListValues\ListValueData;
use App\Data\ListValues\UpdateListValueData;
use App\Http\Controllers\Api\Controller;
use App\Models\ListName;
use App\Models\ListValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ListValueController extends Controller
{
    /**
     * List values for a list name.
     */
    public function index(ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.view', 'static-data:read');

        $values = $listName->values()
            ->orderBy('sort_order')
            ->get()
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
