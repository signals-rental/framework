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
use Dedoc\Scramble\Attributes\BodyParameter;
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

    protected ?string $customFieldModule = 'Activity';

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
        'type',
        'regarding',
        'participants',
        'participants.member',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'type',
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
     *
     * Supports `view_id` query parameter to apply a saved custom view.
     * View filters merge with explicit `q` params (explicit params take priority).
     * View sort applies only when no explicit `sort` param is given.
     */
    #[ApiResponse(200, 'Paginated activity list', type: 'array{activities: list<array{id: int, subject: string, description: string|null, location: string|null, regarding_id: int|null, regarding_type: string|null, owned_by: int, starts_at: string|null, ends_at: string|null, priority: int, type_id: int, status_id: int, completed: bool, time_status: int, custom_fields: array<string, mixed>, participants: list<array{id: int, member_id: int, member_name: string, mute: bool}>, activity_type_name: string, activity_status_name: string, time_status_name: string, created_at: string, updated_at: string, regarding: array{id: int, name: string}|null, owner: array{id: int, name: string}|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single activity.
     */
    #[ApiResponse(200, 'Activity details', type: 'array{activity: array{id: int, subject: string, description: string|null, location: string|null, regarding_id: int|null, regarding_type: string|null, owned_by: int, starts_at: string|null, ends_at: string|null, priority: int, type_id: int, status_id: int, completed: bool, time_status: int, custom_fields: array<string, mixed>, participants: list<array{id: int, member_id: int, member_name: string, mute: bool}>, activity_type_name: string, activity_status_name: string, time_status_name: string, created_at: string, updated_at: string, regarding: array{id: int, name: string}|null, owner: array{id: int, name: string}|null}}')]
    public function show(Request $request, Activity $activity): JsonResponse
    {
        return $this->resourceShow($request, $activity);
    }

    /**
     * Create a new activity.
     */
    #[BodyParameter('subject', 'Short title for the activity.', required: true, type: 'string')]
    #[BodyParameter('description', 'Free-text body of the activity.', required: false, type: 'string|null')]
    #[BodyParameter('location', 'Where the activity takes place.', required: false, type: 'string|null')]
    #[BodyParameter('regarding_type', 'Entity the activity relates to. One of: Member, Product, StockLevel. Required when regarding_id is given.', required: false, type: 'string|null')]
    #[BodyParameter('regarding_id', 'ID of the related entity. Required when regarding_type is given.', required: false, type: 'int|null')]
    #[BodyParameter('owned_by', 'User ID that owns the activity. Defaults to the authenticated user.', required: false, type: 'int|null')]
    #[BodyParameter('starts_at', 'Start timestamp (ISO 8601, UTC).', required: false, type: 'string|null')]
    #[BodyParameter('ends_at', 'End timestamp (ISO 8601, UTC). Must be on or after starts_at.', required: false, type: 'string|null')]
    #[BodyParameter('priority', 'Priority: 0=Low, 1=Normal, 2=High. Defaults to 1.', required: false, type: 'int')]
    #[BodyParameter('type_id', "The 'Activity Type' list_values id (custom list value). Must reference an existing value in the 'Activity Type' list.", required: false, type: 'int|null')]
    #[BodyParameter('status_id', 'Status: 2001=Scheduled, 2002=Completed, 2003=Cancelled, 2004=Held. Defaults to 2001.', required: false, type: 'int')]
    #[BodyParameter('completed', 'Whether the activity is completed. Defaults to false.', required: false, type: 'bool')]
    #[BodyParameter('time_status', 'Calendar busy state: 0=Free, 1=Busy. Defaults to 0.', required: false, type: 'int')]
    #[BodyParameter('participants', 'Members attached to the activity.', required: false, type: 'list<array{member_id: int, mute?: bool}>|null')]
    #[BodyParameter('custom_fields', 'Custom field values keyed by field name.', required: false, type: 'array<string, mixed>')]
    #[ApiResponse(201, 'Activity created', type: 'array{activity: array{id: int, subject: string, description: string|null, location: string|null, regarding_id: int|null, regarding_type: string|null, owned_by: int, starts_at: string|null, ends_at: string|null, priority: int, type_id: int, status_id: int, completed: bool, time_status: int, custom_fields: array<string, mixed>, participants: list<array{id: int, member_id: int, member_name: string, mute: bool}>, activity_type_name: string, activity_status_name: string, time_status_name: string, created_at: string, updated_at: string, regarding: array{id: int, name: string}|null, owner: array{id: int, name: string}|null}}')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing activity.
     */
    #[BodyParameter('subject', 'Short title for the activity.', required: false, type: 'string')]
    #[BodyParameter('description', 'Free-text body of the activity.', required: false, type: 'string|null')]
    #[BodyParameter('location', 'Where the activity takes place.', required: false, type: 'string|null')]
    #[BodyParameter('regarding_type', 'Entity the activity relates to. One of: Member, Product, StockLevel. Required when regarding_id is given.', required: false, type: 'string|null')]
    #[BodyParameter('regarding_id', 'ID of the related entity. Required when regarding_type is given.', required: false, type: 'int|null')]
    #[BodyParameter('owned_by', 'User ID that owns the activity.', required: false, type: 'int|null')]
    #[BodyParameter('starts_at', 'Start timestamp (ISO 8601, UTC).', required: false, type: 'string|null')]
    #[BodyParameter('ends_at', 'End timestamp (ISO 8601, UTC). Must be on or after starts_at.', required: false, type: 'string|null')]
    #[BodyParameter('priority', 'Priority: 0=Low, 1=Normal, 2=High.', required: false, type: 'int|null')]
    #[BodyParameter('type_id', "The 'Activity Type' list_values id (custom list value). Must reference an existing value in the 'Activity Type' list.", required: false, type: 'int|null')]
    #[BodyParameter('status_id', 'Status: 2001=Scheduled, 2002=Completed, 2003=Cancelled, 2004=Held.', required: false, type: 'int|null')]
    #[BodyParameter('completed', 'Whether the activity is completed.', required: false, type: 'bool|null')]
    #[BodyParameter('time_status', 'Calendar busy state: 0=Free, 1=Busy.', required: false, type: 'int|null')]
    #[BodyParameter('participants', 'Members attached to the activity. Replaces the existing set.', required: false, type: 'list<array{member_id: int, mute?: bool}>|null')]
    #[BodyParameter('custom_fields', 'Custom field values keyed by field name.', required: false, type: 'array<string, mixed>|null')]
    #[ApiResponse(200, 'Activity updated', type: 'array{activity: array{id: int, subject: string, description: string|null, location: string|null, regarding_id: int|null, regarding_type: string|null, owned_by: int, starts_at: string|null, ends_at: string|null, priority: int, type_id: int, status_id: int, completed: bool, time_status: int, custom_fields: array<string, mixed>, participants: list<array{id: int, member_id: int, member_name: string, mute: bool}>, activity_type_name: string, activity_status_name: string, time_status_name: string, created_at: string, updated_at: string, regarding: array{id: int, name: string}|null, owner: array{id: int, name: string}|null}}')]
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
    #[ApiResponse(200, 'Activity completed', type: 'array{activity: array{id: int, subject: string, description: string|null, location: string|null, regarding_id: int|null, regarding_type: string|null, owned_by: int, starts_at: string|null, ends_at: string|null, priority: int, type_id: int, status_id: int, completed: bool, time_status: int, custom_fields: array<string, mixed>, participants: list<array{id: int, member_id: int, member_name: string, mute: bool}>, activity_type_name: string, activity_status_name: string, time_status_name: string, created_at: string, updated_at: string, regarding: array{id: int, name: string}|null, owner: array{id: int, name: string}|null}}')]
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
