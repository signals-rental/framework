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
use App\Http\Traits\ResourceActions;
use App\Models\Activity;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use FiltersQueries, ResourceActions;

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

    protected function modelClass(): string
    {
        return Activity::class;
    }

    protected function responseDataClass(): string
    {
        return ActivityData::class;
    }

    protected function createDataClass(): string
    {
        return CreateActivityData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateActivityData::class;
    }

    protected function createActionClass(): string
    {
        return CreateActivity::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateActivity::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteActivity::class;
    }

    protected function singularKey(): string
    {
        return 'activity';
    }

    protected function pluralKey(): string
    {
        return 'activities';
    }

    protected function entityType(): string
    {
        return 'activities';
    }

    protected function permissions(): array
    {
        return ['view' => 'activities.view', 'create' => 'activities.create', 'edit' => 'activities.edit', 'delete' => 'activities.delete'];
    }

    protected function abilities(): array
    {
        return ['read' => 'activities:read', 'write' => 'activities:write'];
    }

    /**
     * List activities with filtering, sorting, and pagination.
     */
    #[ApiResponse(200, 'Paginated activity list')]
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single activity.
     */
    #[ApiResponse(200, 'Activity details')]
    public function show(Request $request, Activity $activity): JsonResponse
    {
        return $this->resourceShow($request, $activity);
    }

    /**
     * Create a new activity.
     */
    #[ApiResponse(201, 'Activity created')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing activity.
     */
    #[ApiResponse(200, 'Activity updated')]
    public function update(Request $request, Activity $activity): JsonResponse
    {
        return $this->resourceUpdate($request, $activity);
    }

    /**
     * Delete an activity.
     */
    #[ApiResponse(204, 'Activity deleted')]
    public function destroy(Activity $activity): JsonResponse
    {
        return $this->resourceDestroy($activity);
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
