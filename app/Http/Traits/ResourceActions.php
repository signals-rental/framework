<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides default CRUD implementations for API resource controllers.
 *
 * Controllers using this trait must also use FiltersQueries and extend
 * App\Http\Controllers\Api\Controller. They define configuration via
 * abstract methods so each controller declares its model, DTOs, actions,
 * response keys, permissions, and abilities.
 */
trait ResourceActions
{
    /**
     * Default index implementation with view resolution, filtering, sorting, and pagination.
     */
    protected function resourceIndex(Request $request): JsonResponse
    {
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        $modelClass = $this->modelClass();
        $query = $modelClass::query();
        $query = $this->applyIncludes($query, $request);

        ['query' => $query, 'view' => $view] = $this->applyViewOrFilters($query, $request, $this->entityType());

        $paginator = $this->paginateQuery($query, $request);

        $dataClass = $this->responseDataClass();
        $items = $paginator->getCollection()->map(
            fn ($model): array => $dataClass::fromModel($model)->toArray()
        )->all();

        $meta = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'page' => $paginator->currentPage(),
        ];

        if ($view !== null) {
            $meta['view'] = [
                'id' => $view->id,
                'name' => $view->name,
            ];
        }

        return response()->json([
            $this->pluralKey() => $items,
            'meta' => $meta,
        ]);
    }

    /**
     * Default show implementation.
     */
    protected function resourceShow(Request $request, mixed $model): JsonResponse
    {
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        $modelClass = $this->modelClass();
        $this->applyIncludes($modelClass::query(), $request, $model);

        $dataClass = $this->responseDataClass();

        return $this->respondWith(
            $dataClass::fromModel($model)->toArray(),
            $this->singularKey(),
        );
    }

    /**
     * Default store implementation.
     */
    protected function resourceStore(Request $request): JsonResponse
    {
        $this->authorizeApi($this->permissions()['create'], $this->abilities()['write']);

        $createDataClass = $this->createDataClass();
        $validated = $request->validate($createDataClass::rules());
        $dto = $createDataClass::from($validated);

        $createActionClass = $this->createActionClass();
        $result = (new $createActionClass)($dto);

        return $this->respondWith(
            $result->toArray(),
            $this->singularKey(),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Default update implementation.
     */
    protected function resourceUpdate(Request $request, mixed $model): JsonResponse
    {
        $this->authorizeApi($this->permissions()['edit'], $this->abilities()['write']);

        $updateDataClass = $this->updateDataClass();
        $validated = $request->validate($updateDataClass::rules());
        $dto = $updateDataClass::from($validated);

        $updateActionClass = $this->updateActionClass();
        $result = (new $updateActionClass)($model, $dto);

        return $this->respondWith(
            $result->toArray(),
            $this->singularKey(),
        );
    }

    /**
     * Default destroy implementation.
     */
    protected function resourceDestroy(mixed $model): JsonResponse
    {
        $this->authorizeApi($this->permissions()['delete'], $this->abilities()['write']);

        $deleteActionClass = $this->deleteActionClass();
        (new $deleteActionClass)($model);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // --- Configuration methods (override in each controller) ---

    abstract protected function modelClass(): string;

    abstract protected function responseDataClass(): string;

    abstract protected function createDataClass(): string;

    abstract protected function updateDataClass(): string;

    abstract protected function createActionClass(): string;

    abstract protected function updateActionClass(): string;

    abstract protected function deleteActionClass(): string;

    abstract protected function singularKey(): string;

    abstract protected function pluralKey(): string;

    abstract protected function entityType(): string;

    /**
     * @return array{view: string, create: string, edit: string, delete: string}
     */
    abstract protected function permissions(): array;

    /**
     * @return array{read: string, write: string}
     */
    abstract protected function abilities(): array;
}
