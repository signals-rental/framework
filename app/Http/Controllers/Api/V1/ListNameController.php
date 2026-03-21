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
use App\Http\Traits\ResourceActions;
use App\Models\ListName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListNameController extends Controller
{
    use FiltersQueries, ResourceActions;

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

    protected function modelClass(): string
    {
        return ListName::class;
    }

    protected function responseDataClass(): string
    {
        return ListNameData::class;
    }

    protected function createDataClass(): string
    {
        return CreateListNameData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateListNameData::class;
    }

    protected function createActionClass(): string
    {
        return CreateListName::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateListName::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteListName::class;
    }

    protected function singularKey(): string
    {
        return 'list_name';
    }

    protected function pluralKey(): string
    {
        return 'list_names';
    }

    protected function entityType(): string
    {
        return 'list_names';
    }

    protected function permissions(): array
    {
        return ['view' => 'list-values.view', 'create' => 'list-values.manage', 'edit' => 'list-values.manage', 'delete' => 'list-values.manage'];
    }

    protected function abilities(): array
    {
        return ['read' => 'static-data:read', 'write' => 'static-data:write'];
    }

    /**
     * List all list names.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single list name with its values.
     */
    public function show(Request $request, ListName $listName): JsonResponse
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
        return $this->resourceStore($request);
    }

    /**
     * Update a list name.
     */
    public function update(Request $request, ListName $listName): JsonResponse
    {
        return $this->resourceUpdate($request, $listName);
    }

    /**
     * Delete a list name.
     */
    public function destroy(ListName $listName): JsonResponse
    {
        return $this->resourceDestroy($listName);
    }
}
