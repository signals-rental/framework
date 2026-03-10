<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\ActionLogData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ActionLog;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionLogController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'action',
        'auditable_type',
        'auditable_id',
        'user_id',
        'created_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'created_at',
        'action',
    ];

    /**
     * List action log entries with optional filtering and pagination.
     *
     * @operationId listActions
     */
    #[ApiResponse(200, 'Paginated action log entries', type: 'array{actions: list<array{id: int, user_id: int|null, user_name: string|null, action: string, auditable_type: string|null, auditable_id: int|null, old_values: array<string, mixed>|null, new_values: array<string, mixed>|null, ip_address: string|null, created_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('action-log.view', 'action-log:read');

        $query = ActionLog::query()->with('user');
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        // Default to newest-first when no explicit sort is requested.
        if (! $request->has('sort')) {
            $query->latest();
        }

        $paginator = $this->paginateQuery($query, $request);

        $actions = $paginator->getCollection()->map(
            fn (ActionLog $log): array => ActionLogData::fromModel($log)->toArray()
        )->all();

        return $this->respondWithCollection($actions, 'actions', $paginator);
    }
}
