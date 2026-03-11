<?php

namespace App\Data\Api;

use App\Models\Webhook;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class WebhookData extends Data
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public int $id,
        public string $url,
        public array $events,
        public bool $is_active,
        public int $consecutive_failures,
        public ?string $disabled_at,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Webhook $webhook): self
    {
        /** @var Carbon|null $disabledAt */
        $disabledAt = $webhook->disabled_at;

        /** @var Carbon|null $createdAt */
        $createdAt = $webhook->created_at;

        /** @var Carbon|null $updatedAt */
        $updatedAt = $webhook->updated_at;

        return new self(
            id: $webhook->id,
            url: $webhook->url,
            events: $webhook->events ?? [],
            is_active: (bool) $webhook->is_active,
            consecutive_failures: (int) $webhook->consecutive_failures,
            disabled_at: $disabledAt?->toIso8601String(),
            created_at: $createdAt?->toIso8601String(),
            updated_at: $updatedAt?->toIso8601String(),
        );
    }
}
