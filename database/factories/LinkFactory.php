<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linkable_type' => Member::class,
            'linkable_id' => Member::factory(),
            'url' => fake()->url(),
            'name' => fake()->optional()->domainName(),
        ];
    }
}
