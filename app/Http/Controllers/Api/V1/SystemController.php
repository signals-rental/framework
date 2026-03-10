<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    /**
     * Return a health check response.
     *
     * Requires the `system.read` permission and `system:read` token ability.
     */
    #[ApiResponse(200, 'Health check', type: 'array{health: array{status: string, timestamp: string}}')]
    public function health(): JsonResponse
    {
        $this->authorizeApi('system.read', 'system:read');

        return $this->respondWith([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ], 'health');
    }
}
