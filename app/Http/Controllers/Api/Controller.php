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
