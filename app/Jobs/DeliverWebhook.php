<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum retry attempts (6 retries with exponential backoff).
     */
    public int $tries = 6;

    /**
     * The webhook delivery timeout in seconds.
     */
    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Webhook $webhook,
        public string $event,
        public array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(3, 5))->backoff(1),
        ];
    }

    /**
     * Calculate backoff delays for exponential retry.
     *
     * Retries at: 1min, 5min, 30min, 2hr, 6hr, 12hr.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600, 43200];
    }

    public function handle(): void
    {
        $this->webhook->refresh();

        if (! $this->webhook->is_active) {
            Log::debug('DeliverWebhook: Skipped delivery for inactive webhook', [
                'webhook_id' => $this->webhook->id,
                'event' => $this->event,
            ]);

            return;
        }

        $encoded = json_encode([
            'event' => $this->event,
            'data' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($encoded === false) {
            Log::error('DeliverWebhook: Failed to JSON-encode payload', [
                'webhook_id' => $this->webhook->id,
                'event' => $this->event,
                'json_error' => json_last_error_msg(),
            ]);

            return;
        }

        $jsonPayload = $encoded;

        $signature = WebhookService::sign($jsonPayload, $this->webhook->secret);

        $log = WebhookLog::create([
            'webhook_id' => $this->webhook->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'attempts' => $this->attempts(),
        ]);

        $shouldRetry = false;
        $retryReason = '';

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signals-Signature' => $signature,
                    'X-Signals-Event' => $this->event,
                    'User-Agent' => 'Signals-Webhook/1.0',
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($this->webhook->url);

            $log->update([
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 10000),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $this->webhook->update(['consecutive_failures' => 0]);
            } else {
                $this->recordFailure();

                if ($response->serverError()) {
                    $shouldRetry = true;
                    $retryReason = "Webhook delivery failed with HTTP {$response->status()}";
                } else {
                    Log::warning('DeliverWebhook: Endpoint returned client error', [
                        'webhook_id' => $this->webhook->id,
                        'event' => $this->event,
                        'http_status' => $response->status(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $log->update([
                'response_body' => mb_substr($e->getMessage(), 0, 10000),
            ]);

            $this->recordFailure();

            throw $e;
        }

        if ($shouldRetry) {
            throw new \RuntimeException($retryReason);
        }
    }

    /**
     * Handle a permanently failed job.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('DeliverWebhook permanently failed after all retries', [
            'webhook_id' => $this->webhook->id,
            'event' => $this->event,
            'url' => $this->webhook->url,
            'exception' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }

    /**
     * Record a delivery failure and auto-disable after 18 consecutive failures.
     */
    private function recordFailure(): void
    {
        $this->webhook->increment('consecutive_failures');

        if ($this->webhook->consecutive_failures >= 18) {
            $this->webhook->update([
                'is_active' => false,
                'disabled_at' => now(),
            ]);
        }
    }
}
