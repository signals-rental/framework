<?php

namespace Database\Factories;

use App\Models\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationType>
 */
class NotificationTypeFactory extends Factory
{
    protected $model = NotificationType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'category' => fake()->randomElement(['Users', 'System', 'Opportunities']),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'available_channels' => ['database', 'mail'],
            'default_channels' => ['database'],
            'is_active' => true,
            'is_system' => false,
            'source' => 'core',
        ];
    }

    /**
     * Indicate that the notification type is a system type that cannot be disabled.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_system' => true,
        ]);
    }
}
