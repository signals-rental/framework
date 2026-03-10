<?php

namespace App\Data\Api;

use App\Services\Api\WebhookService;
use Illuminate\Validation\Rule;

class UpdateWebhookData
{
    /**
     * @param  list<string>|null  $events
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?array $events = null,
        public readonly ?bool $is_active = null,
    ) {}

    /**
     * @param  array{url?: string, events?: list<string>, is_active?: bool}  $data
     */
    public static function from(array $data): self
    {
        return new self(
            url: $data['url'] ?? null,
            events: $data['events'] ?? null,
            is_active: $data['is_active'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookService::EVENTS)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
