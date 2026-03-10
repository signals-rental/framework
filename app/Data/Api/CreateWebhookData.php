<?php

namespace App\Data\Api;

use App\Services\Api\WebhookService;
use Illuminate\Validation\Rule;

class CreateWebhookData
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public readonly string $url,
        public readonly array $events,
    ) {}

    /**
     * @param  array{url: string, events: list<string>}  $data
     */
    public static function from(array $data): self
    {
        return new self(
            url: $data['url'],
            events: $data['events'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookService::EVENTS)],
        ];
    }
}
