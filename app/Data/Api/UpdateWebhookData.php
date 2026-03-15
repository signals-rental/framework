<?php

namespace App\Data\Api;

use App\Services\Api\WebhookService;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateWebhookData extends Data
{
    /**
     * @param  list<string>|null  $events
     */
    public function __construct(
        public ?string $url = null,
        public ?array $events = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in([...WebhookService::EVENTS, '*'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
