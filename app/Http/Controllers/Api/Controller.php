<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    /**
     * Whitelisted fields for Ransack filtering. Controllers override as needed.
     *
     * @var list<string>
     */
    protected array $allowedFilters = [];

    /**
     * Maps response output keys to their underlying filterable/sortable column,
     * so consumers can filter/sort by the field name they receive in responses
     * (e.g. ['type' => 'product_type', 'active' => 'is_active']). The target
     * column must still appear in $allowedFilters/$allowedSorts.
     *
     * @var array<string, string>
     */
    protected array $filterAliases = [];

    /**
     * Whitelisted relations for Ransack relation filtering, as a map of
     * relation => allowed columns (e.g. ['productGroup' => ['name']]).
     *
     * @var array<string, list<string>>
     */
    protected array $allowedRelationFilters = [];

    /**
     * Whitelisted fields for sorting.
     *
     * @var list<string>
     */
    protected array $allowedSorts = [];

    /**
     * Whitelisted relationships available via ?include=.
     *
     * @var list<string>
     */
    protected array $allowedIncludes = [];

    /**
     * Relationships always eager-loaded.
     *
     * @var list<string>
     */
    protected array $defaultIncludes = [];

    /**
     * Custom field module type for cf.* filters, or null when unsupported.
     */
    protected ?string $customFieldModule = null;

    /**
     * Wrap a single resource in a keyed JSON response.
     *
     * @param  array<string, mixed>|object  $data
     */
    protected function respondWith(mixed $data, string $key, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([$key => $data], $status);
    }

    /**
     * Wrap a collection of resources with pagination metadata.
     *
     * @param  array<int, mixed>|object  $items
     * @param  LengthAwarePaginator<int, mixed>|null  $paginator
     */
    protected function respondWithCollection(
        mixed $items,
        string $key,
        ?LengthAwarePaginator $paginator = null,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        $response = [$key => $items];

        if ($paginator) {
            $response['meta'] = [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ];
        } else {
            $itemCount = is_array($items) ? count($items) : 0;
            $response['meta'] = [
                'total' => $itemCount,
                'per_page' => $itemCount,
                'page' => 1,
            ];
        }

        return response()->json($response, $status);
    }

    /**
     * Return a JSON error response.
     *
     * @param  array<string, list<string>>|null  $errors
     */
    protected function respondWithError(
        string $message,
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        ?array $errors = null,
    ): JsonResponse {
        $response = ['message' => $message];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a 202 Accepted response for async operations.
     */
    protected function respondAccepted(?string $jobId = null): JsonResponse
    {
        $response = ['message' => 'Accepted'];

        if ($jobId !== null) {
            $response['job_id'] = $jobId;
        }

        return response()->json($response, Response::HTTP_ACCEPTED);
    }

    /**
     * Authorize the request using both Gate permission and Sanctum token ability.
     *
     * Gate permissions use dot notation (e.g. "system.read") and are checked
     * against the user's Spatie roles/permissions. Token abilities use colon
     * notation (e.g. "system:read") and are checked against the Sanctum token's
     * granted abilities.
     *
     * Owner users bypass Gate checks (via Gate::before in AppServiceProvider)
     * but must still hold the correct token ability.
     */
    protected function authorizeApi(string $permission, string $ability): void
    {
        Gate::authorize($permission);

        /** @var PersonalAccessToken|null $token */
        $token = request()->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability)) {
            abort(Response::HTTP_FORBIDDEN, "Token does not have the required ability: {$ability}");
        }
    }
}
