<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\SettingsRegistry;
use App\Services\SettingsService;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsRegistry $registry,
        private SettingsService $settings,
    ) {}

    /**
     * List all settings groups with their current values.
     *
     * Requires the `settings.view` permission and `settings:read` token ability.
     */
    #[ApiResponse(200, 'All settings groups', type: 'array{settings: list<array{group: string, settings: array<string, mixed>}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(): JsonResponse
    {
        $this->authorizeApi('settings.view', 'settings:read');

        $groups = [];

        foreach ($this->registry->all() as $definition) {
            $groups[] = [
                'group' => $definition->group(),
                'settings' => $this->settings->group($definition->group()),
            ];
        }

        return $this->respondWithCollection($groups, 'settings');
    }

    /**
     * Get settings for a specific group.
     *
     * Requires the `settings.view` permission and `settings:read` token ability.
     */
    #[ApiResponse(200, 'Settings for a group', type: 'array{setting: array{group: string, settings: array<string, mixed>}}')]
    public function show(string $group): JsonResponse
    {
        $this->authorizeApi('settings.view', 'settings:read');

        if (! $this->registry->has($group)) {
            return $this->respondWithError('Settings group not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->respondWith([
            'group' => $group,
            'settings' => $this->settings->group($group),
        ], 'setting');
    }

    /**
     * Update settings for a specific group.
     *
     * Requires the `settings.manage` permission and `settings:write` token ability.
     * Accepts partial updates — only provided keys are validated and persisted.
     *
     * @param  string  $group  The settings group name
     */
    #[ApiResponse(200, 'Updated settings', type: 'array{setting: array{group: string, settings: array<string, mixed>}}')]
    public function update(string $group, Request $request): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'settings:write');

        if (! $this->registry->has($group)) {
            return $this->respondWithError('Settings group not found.', Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $rawInput */
        $rawInput = $request->input('settings', []);

        /** @var \App\Settings\SettingsDefinition $definition */
        $definition = $this->registry->get($group);

        $allRules = $definition->rules();

        // Only accept keys that are defined in the settings schema.
        $input = array_intersect_key($rawInput, $allRules);

        // Only validate keys that were actually provided (partial updates).
        $rules = array_intersect_key($allRules, $input);

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return $this->respondWithError(
                'The given data was invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
            );
        }

        $types = $definition->types();

        foreach ($input as $key => $value) {
            $type = $types[$key] ?? 'string';
            $this->settings->set("{$group}.{$key}", $value, $type);
        }

        app(\App\Services\Api\WebhookService::class)->dispatch('settings.updated', [
            'group' => $group,
            'settings' => $this->settings->group($group),
        ]);

        return $this->respondWith([
            'group' => $group,
            'settings' => $this->settings->group($group),
        ], 'setting');
    }
}
