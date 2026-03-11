<?php

namespace App\Data\Api;

use App\Services\Api\WebhookService;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class CreateWebhookData extends Data
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public string $url,
        public array $events,
    ) {}

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
