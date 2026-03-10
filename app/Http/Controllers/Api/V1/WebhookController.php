<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\CreateWebhookData;
use App\Data\Api\UpdateWebhookData;
use App\Data\Api\WebhookData;
use App\Data\Api\WebhookLogData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'url',
        'is_active',
        'created_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'url',
        'created_at',
    ];

    /**
     * List all webhooks for the authenticated user.
     *
     * @operationId listWebhooks
     */
    #[ApiResponse(200, 'User webhooks', type: 'array{webhooks: list<array{id: int, url: string, events: list<string>, is_active: bool, consecutive_failures: int, disabled_at: string|null, created_at: string|null, updated_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');

        $query = Webhook::query()->where('user_id', $request->user()->id);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->latest();
        }

        $paginator = $this->paginateQuery($query, $request);

        $webhooks = $paginator->getCollection()->map(
            fn (Webhook $w): array => WebhookData::fromModel($w)->toArray()
        )->all();

        return $this->respondWithCollection($webhooks, 'webhooks', $paginator);
    }

    /**
     * Show a single webhook.
     *
     * @operationId getWebhook
     */
    #[ApiResponse(200, 'Webhook details', type: 'array{webhook: array{id: int, url: string, events: list<string>, is_active: bool, consecutive_failures: int, disabled_at: string|null, created_at: string|null, updated_at: string|null}}')]
    public function show(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        return $this->respondWith(WebhookData::fromModel($webhook)->toArray(), 'webhook');
    }

    /**
     * Register a new webhook.
     *
     * @operationId createWebhook
     */
    #[ApiResponse(201, 'Webhook created (includes secret)', type: 'array{webhook: array{id: int, url: string, events: list<string>, is_active: bool, consecutive_failures: int, disabled_at: string|null, created_at: string|null, updated_at: string|null, secret: string}}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');

        $validated = $request->validate(CreateWebhookData::rules());
        $dto = CreateWebhookData::from($validated);

        $webhook = Webhook::create([
            'user_id' => $request->user()->id,
            'url' => $dto->url,
            'secret' => Str::random(32),
            'events' => $dto->events,
            'is_active' => true,
        ]);

        // Return the secret only on creation
        $data = WebhookData::fromModel($webhook)->toArray();
        $data['secret'] = $webhook->secret;

        return $this->respondWith($data, 'webhook', Response::HTTP_CREATED);
    }

    /**
     * Update an existing webhook.
     *
     * @operationId updateWebhook
     */
    #[ApiResponse(200, 'Webhook updated', type: 'array{webhook: array{id: int, url: string, events: list<string>, is_active: bool, consecutive_failures: int, disabled_at: string|null, created_at: string|null, updated_at: string|null}}')]
    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        $validated = $request->validate(UpdateWebhookData::rules());
        $dto = UpdateWebhookData::from($validated);

        $updates = array_filter([
            'url' => $dto->url,
            'events' => $dto->events,
            'is_active' => $dto->is_active,
        ], fn ($v) => $v !== null);

        // Reset failure counter when re-enabling
        if (($dto->is_active ?? false) && ! $webhook->is_active) {
            $updates['consecutive_failures'] = 0;
            $updates['disabled_at'] = null;
        }

        $webhook->update($updates);

        /** @var Webhook $freshWebhook */
        $freshWebhook = $webhook->fresh();

        return $this->respondWith(WebhookData::fromModel($freshWebhook)->toArray(), 'webhook');
    }

    /**
     * Delete a webhook.
     *
     * @operationId deleteWebhook
     */
    #[ApiResponse(204, 'Webhook deleted')]
    public function destroy(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        $webhook->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * List delivery logs for a webhook.
     *
     * @operationId listWebhookLogs
     */
    #[ApiResponse(200, 'Webhook delivery logs', type: 'array{logs: list<array{id: int, event: string, response_code: int|null, attempts: int, delivered_at: string|null, created_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function logs(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        $query = WebhookLog::query()
            ->where('webhook_id', $webhook->id)
            ->latest();

        $paginator = $this->paginateQuery($query, $request);

        /** @var list<WebhookLog> $items */
        $items = $paginator->items();

        $logs = collect($items)
            ->map(fn (WebhookLog $log): array => WebhookLogData::fromModel($log)->toArray())
            ->all();

        return $this->respondWithCollection($logs, 'logs', $paginator);
    }

    /**
     * Ensure the webhook belongs to the authenticated user.
     */
    private function authorizeOwnership(Webhook $webhook, Request $request): void
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($webhook->user_id !== $user->id && ! $user->isOwner()) {
            abort(Response::HTTP_FORBIDDEN, 'You do not own this webhook.');
        }
    }
}
