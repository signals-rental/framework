<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Views\CloneCustomView;
use App\Actions\Views\CreateCustomView;
use App\Actions\Views\DeleteCustomView;
use App\Actions\Views\UpdateCustomView;
use App\Data\Views\CreateCustomViewData;
use App\Data\Views\CustomViewData;
use App\Data\Views\UpdateCustomViewData;
use App\Http\Controllers\Api\Controller;
use App\Models\CustomView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomViewController extends Controller
{
    /**
     * List custom views for an entity type, visible to the current user.
     *
     * @response array{custom_views: list<CustomViewData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomView::query();

        $entityType = $request->input('entity_type');
        if ($entityType) {
            $query->forEntity($entityType);
        }

        $query->visibleTo($request->user());
        $query->orderBy('visibility')
            ->orderBy('name');

        $views = $query->get()->map(
            fn (CustomView $view): array => CustomViewData::fromModel($view)->toArray()
        )->all();

        return $this->respondWithCollection($views, 'custom_views');
    }

    /**
     * Show a single custom view.
     *
     * @response array{custom_view: CustomViewData}
     */
    public function show(CustomView $customView): JsonResponse
    {
        return $this->respondWith(
            CustomViewData::fromModel($customView)->toArray(),
            'custom_view',
        );
    }

    /**
     * Create a new custom view.
     *
     * @response 201 array{custom_view: CustomViewData}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(CreateCustomViewData::rules());
        $dto = CreateCustomViewData::from($validated);

        $result = (new CreateCustomView)($dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_view',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing custom view.
     *
     * @response array{custom_view: CustomViewData}
     */
    public function update(Request $request, CustomView $customView): JsonResponse
    {
        $validated = $request->validate(UpdateCustomViewData::rules());
        $dto = UpdateCustomViewData::from($validated);

        $result = (new UpdateCustomView)($customView, $dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_view',
        );
    }

    /**
     * Delete a custom view.
     */
    public function destroy(CustomView $customView): JsonResponse
    {
        (new DeleteCustomView)($customView);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Clone a custom view as a personal copy.
     *
     * @response 201 array{custom_view: CustomViewData}
     */
    public function clone(CustomView $customView): JsonResponse
    {
        $result = (new CloneCustomView)($customView);

        return $this->respondWith(
            $result->toArray(),
            'custom_view',
            Response::HTTP_CREATED,
        );
    }
}
