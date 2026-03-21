<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Activities\CompleteActivity;
use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\DeleteActivity;
use App\Actions\Activities\UpdateActivity;
use App\Data\Activities\ActivityData;
use App\Data\Activities\CreateActivityData;
use App\Data\Activities\UpdateActivityData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Activity;
use App\Services\ViewResolver;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ActivityController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'subject',
        'type_id',
        'status_id',
        'priority',
        'regarding_type',
        'regarding_id',
        'owned_by',
        'completed',
        'starts_at',
        'ends_at',
        'created_at',
    ];

    protected string $customFieldModule = 'Activity';

    /** @var list<string> */
    protected array $allowedSorts = [
        'subject',
        'type_id',
        'status_id',
        'priority',
        'starts_at',
        'ends_at',
        'created_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'owner',
        'regarding',
        'participants',
        'participants.member',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'customFieldValues',
    ];

    /**
     * List activities with filtering, sorting, and pagination.
     */
    #[ApiResponse(200, 'Paginated activity list')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('activities.view', 'activities:read');

        $query = Activity::query();
        $query = $this->applyIncludes($query, $request);

        $viewId = $request->filled('view_id') ? (int) $request->input('view_id') : null;
        $viewResolver = app(ViewResolver::class);
        $view = $viewResolver->resolve('activities', $viewId, $request->user());

        if ($view !== null) {
            $explicitFilters = $request->input('q', []);
            if (! is_array($explicitFilters)) {
                $explicitFilters = [];
            }
            $query = $viewResolver->applyFilters($query, $view, $explicitFilters);

            if (! $request->filled('sort')) {
                $query = $viewResolver->applySort($query, $view);
            } else {
                $query = $this->applySort($query, $request);
            }
        } else {
            $query = $this->applyFilters($query, $request);
            $query = $this->applySort($query, $request);
        }

        /** @var LengthAwarePaginator<int, Activity> $paginator */
        $paginator = $this->paginateQuery($query, $request);

        $activities = $paginator->getCollection()->map(
            fn (Activity $activity): array => ActivityData::fromModel($activity)->toArray()
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
            'activities' => $activities,
            'meta' => $meta,
        ]);
    }

    /**
     * Show a single activity.
     */
    #[ApiResponse(200, 'Activity details')]
    public function show(Request $request, Activity $activity): JsonResponse
    {
        $this->authorizeApi('activities.view', 'activities:read');

        $this->applyIncludes(Activity::query(), $request, $activity);

        return $this->respondWith(
            ActivityData::fromModel($activity)->toArray(),
            'activity',
        );
    }

    /**
     * Create a new activity.
     */
    #[ApiResponse(201, 'Activity created')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('activities.create', 'activities:write');

        $validated = $request->validate(CreateActivityData::rules());
        $dto = CreateActivityData::from($validated);

        $result = (new CreateActivity)($dto);

        return $this->respondWith(
            $result->toArray(),
            'activity',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing activity.
     */
    #[ApiResponse(200, 'Activity updated')]
    public function update(Request $request, Activity $activity): JsonResponse
    {
        $this->authorizeApi('activities.edit', 'activities:write');

        $validated = $request->validate(UpdateActivityData::rules());
        $dto = UpdateActivityData::from($validated);

        $result = (new UpdateActivity)($activity, $dto);

        return $this->respondWith(
            $result->toArray(),
            'activity',
        );
    }

    /**
     * Delete an activity.
     */
    #[ApiResponse(204, 'Activity deleted')]
    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorizeApi('activities.delete', 'activities:write');

        (new DeleteActivity)($activity);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Mark an activity as completed.
     */
    #[ApiResponse(200, 'Activity completed')]
    public function complete(Activity $activity): JsonResponse
    {
        $this->authorizeApi('activities.complete', 'activities:write');

        $result = (new CompleteActivity)($activity);

        return $this->respondWith(
            $result->toArray(),
            'activity',
        );
    }
}
