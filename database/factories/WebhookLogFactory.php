<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event' => 'user.created',
            'payload' => ['user' => ['id' => 1, 'name' => 'Test']],
            'response_code' => 200,
            'response_body' => 'OK',
            'attempts' => 1,
            'delivered_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'response_code' => 500,
            'response_body' => 'Internal Server Error',
            'delivered_at' => null,
        ]);
    }
}
