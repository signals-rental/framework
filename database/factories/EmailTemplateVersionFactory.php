<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplateVersion>
 */
class EmailTemplateVersionFactory extends Factory
{
    protected $model = EmailTemplateVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_template_id' => EmailTemplate::factory(),
            'subject' => fake()->sentence(4),
            'body_markdown' => fake()->paragraph(),
            'version_number' => 1,
            'created_by' => User::factory(),
        ];
    }
}
